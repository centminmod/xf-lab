#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

fatal() {
  echo "ERROR: $*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fatal "Missing command: $1"
}

compose_cmd() {
  docker compose "$@"
}

normalise_instance_name() {
  local xf_version="$1"
  local php_version="$2"
  echo "xf-${xf_version}-php-${php_version}"
}

normalise_project_name() {
  local xf_version="$1"
  local php_version="$2"
  echo "xf_${xf_version}_php_${php_version}" | tr '.+-/' '____' | tr -cd '[:alnum:]_'
}

calculate_port() {
  local xf_version="$1"
  local php_version="$2"
  local xf_minor php_major php_minor
  xf_minor="$(echo "$xf_version" | awk -F. '{print $2+0}')"
  php_major="$(echo "$php_version" | awk -F. '{print $1+0}')"
  php_minor="$(echo "$php_version" | awk -F. '{print $2+0}')"
  echo $((8000 + (xf_minor * 100) + (php_major * 10) + php_minor))
}

abs_path() {
  python3 -c 'import os, sys; print(os.path.abspath(sys.argv[1]))' "$1"
}

resolve_addon_source() {
  local addon_id="$1"
  local addon_source="${ADDON_SOURCE:-}"
  local id_path="$addon_id"

  if [[ -z "$addon_id" ]]; then
    return 1
  fi

  if [[ -n "$addon_source" ]]; then
    local candidate="$addon_source"
    if [[ -f "$candidate/addon.json" ]]; then
      abs_path "$candidate"
      return 0
    fi
    if [[ -f "$candidate/src/addons/$id_path/addon.json" ]]; then
      abs_path "$candidate/src/addons/$id_path"
      return 0
    fi
    if [[ -f "$candidate/addons/$id_path/addon.json" ]]; then
      abs_path "$candidate/addons/$id_path"
      return 0
    fi
    fatal "ADDON_SOURCE was set, but I could not find addon.json for $addon_id under: $addon_source"
  fi

  if [[ -f "$ROOT_DIR/addons/$id_path/addon.json" ]]; then
    abs_path "$ROOT_DIR/addons/$id_path"
    return 0
  fi

  fatal "ADDON_ID=$addon_id was set, but no add-on was found at addons/$id_path/addon.json. Either put it there or set ADDON_SOURCE=/absolute/path/to/$id_path"
}

looks_like_zip_archive() {
  local value="$1"
  [[ "$value" == *.zip || "$value" == *.ZIP ]]
}

resolve_addon_archive() {
  local archive_spec="$1"
  local candidate

  [[ -n "$archive_spec" ]] || fatal "No add-on archive was supplied"

  for candidate in \
    "$archive_spec" \
    "$ROOT_DIR/$archive_spec" \
    "$ROOT_DIR/addons/$archive_spec" \
    "$ROOT_DIR/archives/$archive_spec"; do
    if [[ -f "$candidate" ]]; then
      abs_path "$candidate"
      return 0
    fi
  done

  fatal "Could not find add-on archive '$archive_spec'. Tried the path as supplied, project root, addons/, and archives/."
}

assert_safe_zip_paths() {
  local archive="$1"
  python3 - "$archive" <<'PY'
import sys
import zipfile
from pathlib import PurePosixPath

archive = sys.argv[1]
try:
    with zipfile.ZipFile(archive) as zf:
        bad = []
        for info in zf.infolist():
            name = info.filename.replace('\\', '/')
            path = PurePosixPath(name)
            if (
                not name
                or name.startswith('/')
                or path.is_absolute()
                or any(part in ('', '..') for part in path.parts)
            ):
                bad.append(info.filename)
        if bad:
            print('Unsafe path(s) in ZIP archive:', file=sys.stderr)
            for name in bad[:20]:
                print(f'  {name}', file=sys.stderr)
            if len(bad) > 20:
                print(f'  ... and {len(bad) - 20} more', file=sys.stderr)
            sys.exit(1)
except zipfile.BadZipFile:
    print(f'Not a valid ZIP archive: {archive}', file=sys.stderr)
    sys.exit(1)
PY
}

find_addon_upload_root() {
  local extract_dir="$1"

  python3 - "$extract_dir" <<'PY'
import os
import sys

extract_dir = os.path.abspath(sys.argv[1])

def depth_from_root(path):
    rel = os.path.relpath(path, extract_dir)
    if rel == '.':
        return 0
    return rel.count(os.sep) + 1

# Normal XenForo release/add-on archive shape: upload/ at archive root.
direct_upload = os.path.join(extract_dir, 'upload')
if os.path.isdir(os.path.join(direct_upload, 'src', 'addons')):
    print(direct_upload)
    sys.exit(0)

# Archives are sometimes wrapped in a top-level folder. Look for an upload/
# directory, but keep this shallow so a random nested directory does not win.
for current_root, dirs, _files in os.walk(extract_dir):
    dirs[:] = sorted(d for d in dirs if d not in {'.git', '__MACOSX'})
    if depth_from_root(current_root) > 4:
        dirs[:] = []
        continue
    if os.path.basename(current_root) == 'upload' and os.path.isdir(os.path.join(current_root, 'src', 'addons')):
        print(current_root)
        sys.exit(0)

# Some developer-made archives are already rooted at the XenForo webroot
# instead of wrapping their files in upload/. Accept them as a convenience.
if os.path.isdir(os.path.join(extract_dir, 'src', 'addons')):
    print(extract_dir)
    sys.exit(0)

sys.exit(1)
PY
}

discover_addon_ids_from_upload_root() {
  local upload_root="$1"
  local addons_root="$upload_root/src/addons"

  [[ -d "$addons_root" ]] || return 1

  python3 - "$addons_root" <<'PY'
import os
import sys

addons_root = os.path.abspath(sys.argv[1])
found = []

for current_root, dirs, files in os.walk(addons_root):
    rel = os.path.relpath(current_root, addons_root)
    depth = 0 if rel == '.' else rel.count(os.sep) + 1

    dirs[:] = sorted(d for d in dirs if not d.startswith('_'))
    if depth >= 6:
        dirs[:] = []

    if 'addon.json' not in files or rel == '.':
        continue

    addon_id = rel.replace(os.sep, '/')
    if addon_id.startswith('_') or '/_' in addon_id:
        continue
    found.append(addon_id)

for addon_id in sorted(found):
    print(addon_id)
PY
}

install_addon_archive_to_webroot() {
  local archive="$1"
  local webroot="$2"
  local work_parent="$3"
  local extract_dir upload_root

  [[ -f "$archive" ]] || fatal "Add-on archive not found: $archive"
  [[ -d "$webroot" ]] || fatal "Webroot does not exist: $webroot"

  assert_safe_zip_paths "$archive"

  extract_dir="$(mktemp -d "$work_parent/addon-archive.XXXXXX")"
  unzip -q "$archive" -d "$extract_dir"

  upload_root="$(find_addon_upload_root "$extract_dir" || true)"
  if [[ -z "$upload_root" ]]; then
    rm -rf "$extract_dir"
    fatal "Could not find a XenForo add-on upload root in $archive. Expected upload/src/addons/<Vendor>/<AddOn>/addon.json, or an archive already rooted at src/addons/."
  fi

  _xf_lab_archive_addon_ids=()
  while IFS= read -r addon_id; do
    [[ -n "$addon_id" ]] && _xf_lab_archive_addon_ids+=("$addon_id")
  done < <(discover_addon_ids_from_upload_root "$upload_root" || true)

  if [[ "${#_xf_lab_archive_addon_ids[@]}" -eq 0 ]]; then
    rm -rf "$extract_dir"
    fatal "Could not find any addon.json under $upload_root/src/addons in $archive"
  fi

  echo "Installing add-on archive files into webroot:" >&2
  echo "  Archive: $archive" >&2
  echo "  Source:  $upload_root" >&2
  echo "  Target:  $webroot" >&2

  cp -a "$upload_root/." "$webroot/"
  rm -rf "$extract_dir"

  printf '%s\n' "${_xf_lab_archive_addon_ids[@]}"
}

wait_for_db() {
  local compose_file="$1"
  local project_name="$2"
  local ping_bin="${3:-mariadb-admin}"
  local max=90
  local i
  for ((i=1; i<=max; i++)); do
    if compose_cmd -f "$compose_file" -p "$project_name" exec -T db "$ping_bin" ping -uroot -proot --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  fatal "Database did not become ready in time. Run: docker compose -f $compose_file -p $project_name logs db"
}


calculate_ngrok_api_port() {
  local xf_version="$1"
  local php_version="$2"
  local xf_minor php_major php_minor
  xf_minor="$(echo "$xf_version" | awk -F. '{print $2+0}')"
  php_major="$(echo "$php_version" | awk -F. '{print $1+0}')"
  php_minor="$(echo "$php_version" | awk -F. '{print $2+0}')"
  echo $((5000 + (xf_minor * 100) + (php_major * 10) + php_minor))
}

shell_quote() {
  python3 -c 'import shlex, sys; print(shlex.quote(sys.argv[1]))' "$1"
}
