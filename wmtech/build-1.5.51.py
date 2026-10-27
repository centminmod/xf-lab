#!/usr/bin/env python3
"""Rebuild the licensed upgrade ZIP from the unchanged supplied 1.5.50 archive."""
from pathlib import Path, PurePosixPath
import hashlib
import json
import re
import zipfile

ROOT = Path(__file__).resolve().parent
BASE_SHA256 = "397ec6b51e81f7574dcbcd638aae8f5dfdde0d7976fe14e7fd185af142d5164a"


def build():
    source = ROOT / "xenforo_1.5.50_upgrade.zip"
    assert hashlib.sha256(source.read_bytes()).hexdigest() == BASE_SHA256, "Wrong base archive"
    with zipfile.ZipFile(source) as archive:
        files = {}
        for item in archive.infolist():
            path = PurePosixPath(item.filename)
            assert not path.is_absolute() and ".." not in path.parts, "Unsafe archive path"
            if not item.is_dir():
                files[item.filename] = archive.read(item)
    release = ROOT / "1.5.51"
    changes = []
    for path in sorted(release.rglob("*")):
        if not path.is_file() or path.name.startswith("."):
            continue
        relative = path.relative_to(release).as_posix()
        files[relative] = path.read_bytes()
        changes.append(relative)
    assert b"1055170" in files["upload/library/XenForo/Application.php"]
    assert "whatsnew.html" in files
    sums = "upload/library/XenForo/Install/Data/FileSums.php"
    keys = set(re.findall(r"'([^']+)'\s*=>\s*'[a-f0-9]{32}'", files[sums].decode()))
    keys.update(path.removeprefix("upload/") for path in changes if path.startswith("upload/"))
    keys.discard(sums.removeprefix("upload/"))
    # Native XF1 health hashes ignore CR bytes; the SHA-256 manifest below uses raw bytes.
    entries = []
    for key in sorted(keys):
        assert "upload/" + key in files, "Missing health-check source: " + key
        entries.append("  '%s' => '%s'," % (key, hashlib.md5(files["upload/" + key].replace(b"\r", b"")).hexdigest()))
    files[sums] = ("<?php\n/* XF1551-PATCH: reproducible native file-health manifest. */\n"
                   "class XenForo_Install_Data_FileSums\n{\n\tpublic static function getHashes()\n\t{\n"
                   "\t\treturn array(\n" + "\n".join(entries) + "\n\t\t);\n\t}\n}\n").encode()
    files["RELEASE.json"] = (json.dumps({"version": "1.5.51", "versionId": 1055170,
        "baseArchiveSha256": BASE_SHA256, "changedSources": changes,
        "nativeHealthEntries": len(keys), "uploadTreeSha256": hashlib.sha256(json.dumps({key: hashlib.sha256(value).hexdigest() for key, value in sorted(files.items()) if key.startswith("upload/")}, sort_keys=True).encode()).hexdigest(), "reproducibleZipTimestamp": "2026-10-27T00:00:00"}, indent=2) + "\n").encode()
    files["SHA256SUMS.json"] = (json.dumps({key: hashlib.sha256(value).hexdigest()
        for key, value in sorted(files.items())}, indent=2) + "\n").encode()
    output = ROOT / "xenforo_1.5.51_upgrade.zip"
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for key, value in sorted(files.items()):
            item = zipfile.ZipInfo(key, (2026, 10, 27, 0, 0, 0))
            item.compress_type = zipfile.ZIP_DEFLATED
            item.create_system = 3
            item.external_attr = 0o100644 << 16
            archive.writestr(item, value)
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix(output.suffix + ".sha256").write_text(digest + "  " + output.name + "\n")
    with zipfile.ZipFile(output) as archive:
        assert archive.testzip() is None
        recorded = json.loads(archive.read("SHA256SUMS.json"))
        assert all(hashlib.sha256(archive.read(key)).hexdigest() == value for key, value in recorded.items())
        assert not any(key.startswith(("upload/data/", "upload/internal_data/")) or key == "upload/library/config.php" for key in archive.namelist())
    print(json.dumps({"zip": str(output), "sha256": digest, "files": len(files), "healthEntries": len(keys)}))


if __name__ == "__main__":
    build()
