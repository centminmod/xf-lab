# XenForo Docker Test Lab

Disposable Docker/ngrok test environments for XenForo add-on developers.

Spin up a specific XenForo version on a specific PHP version, mount an add-on, optionally expose it through a public HTTPS ngrok tunnel, test what you need, then destroy the whole thing.

This project is intended for development and compatibility testing only.

---

## What this does

`xf-lab` lets you run commands like:

```bash
./scripts/xf-up 2.3.12 8.3
```

or:

```bash
PUBLIC=ngrok ADDON_ID=Vendor/AddOn ./scripts/xf-up 2.1.15 7.2
```

The script will:

1. find the matching XenForo ZIP in `archives/`;
2. extract the XenForo `upload/` files into a disposable instance folder;
3. create `src/config.php`;
4. build/start a PHP/Apache container for the selected PHP version;
5. start a MariaDB database;
6. optionally start an ngrok public HTTPS tunnel;
7. run the XenForo CLI installer;
8. optionally mount and install/rebuild your add-on;
9. print the forum/admin URL and login details.

Generated installs live under `instances/` and can be deleted at any time.

---

## Important licensing notes

This repository does **not** contain XenForo code.

You must have a valid XenForo licence and supply your own XenForo ZIP downloads. Do not commit XenForo ZIPs, extracted XenForo files, generated instances, or commercial add-on files to a public repository.

Public tunnel mode is useful for testing external callbacks/webhooks, but do not leave public test installs running longer than necessary. Treat every instance created by this tool as disposable.

---

## Requirements

You need:

- Docker Desktop or Docker Engine with Docker Compose v2
- `unzip`
- `python3`
- Bash-compatible shell
- optional: `ngrok` for public tunnel mode

On macOS, the usual setup is:

```bash
brew install ngrok
```

Then add your ngrok authtoken once when you first run public mode, or pass it as `NGROK_AUTHTOKEN`.

---

## Directory layout

```text
xf-lab/
  archives/              # put licensed XenForo ZIP files here
  addons/                # optional local add-on source mounts
  docker/php/            # PHP/Apache Dockerfile and PHP ini
  scripts/               # helper commands
  tools/                 # XenForo CLI installer wrapper
  instances/             # generated disposable installs, ignored by git
```

---

## Add your XenForo ZIP files

Put your licensed XenForo downloads in:

```text
archives/
```

Name each file with the version, using **either** a hyphen or an underscore separator
(both are accepted — XenForo's own downloads use the underscore form):

```text
xenforo-2.3.12.zip   or   xenforo_2.3.12.zip
xenforo-2.2.19.zip   or   xenforo_2.2.19.zip
xenforo-1.5.24.zip   or   xenforo_1.5.24.zip
```

You can keep **multiple** release ZIPs in `archives/` at once — the version you pass to
`xf-up` selects exactly one. For example:

```bash
./scripts/xf-up 2.3.12 8.3
```

uses `archives/xenforo-2.3.12.zip` or `archives/xenforo_2.3.12.zip` (whichever exists).

The ZIP can contain the usual XenForo `upload/` directory. The script will locate and copy that into the generated instance.

---

## First run

Make scripts executable:

```bash
chmod +x scripts/* tools/*
```

Start a local-only XenForo instance:

```bash
./scripts/xf-up 2.3.12 8.3
```

You should get output similar to:

```text
Instance ready.

  Forum:  http://localhost:8383/
  Admin:  http://localhost:8383/admin.php
  User:   admin
  Pass:   Admin123Pass
```

The port is calculated from the XenForo/PHP versions so multiple instances can run side by side.

---

## Start with a custom port

```bash
PORT=9090 ./scripts/xf-up 2.3.12 8.3
```

---

## Mount and test an add-on

For an add-on with ID:

```text
Vendor/AddOn
```

place it here:

```text
addons/Vendor/AddOn/addon.json
```

Then run:

```bash
ADDON_ID=Vendor/AddOn ./scripts/xf-up 2.3.12 8.3
```

The add-on is mounted into:

```text
src/addons/Vendor/AddOn
```

inside the generated XenForo install.

### Install/test an add-on release ZIP

XenForo 2 add-on release archives are normally rooted like this:

```text
upload/src/addons/Vendor/AddOn/addon.json
upload/js/...
upload/styles/...
```

That `upload/` directory is relative to the XenForo root, not relative to `src/addons/`. If you pass a ZIP filename to `ADDON_ID`, the lab extracts the archive, copies every file from the archive's `upload/` directory into the generated XenForo webroot, discovers the add-on ID from `upload/src/addons/.../addon.json`, then installs/rebuilds it.

You can put add-on ZIP files in `addons/`, `archives/`, or pass an absolute/relative path:

```bash
ADDON_ID=Zee-BotGuard-1.0.0.zip ./scripts/xf-up 2.3.12 8.3
ADDON_ID=addons/Zee-BotGuard-1.0.0.zip ./scripts/xf-up 2.3.12 8.3
ADDON_ID=/Users/you/Downloads/Zee-BotGuard-1.0.0.zip ./scripts/xf-up 2.3.12 8.3
```

`ADDON_ARCHIVE` is also accepted as a clearer alias:

```bash
ADDON_ARCHIVE=Zee-BotGuard-1.0.0.zip ./scripts/xf-up 2.3.12 8.3
```

For an already-running instance, use the same ZIP input with `xf-addon` to copy the archive files into the existing webroot and run the XenForo CLI action:

```bash
ADDON_ID=Zee-BotGuard-1.0.1.zip ./scripts/xf-addon 2.3.12 8.3 upgrade
ADDON_ID=Zee-BotGuard-1.0.1.zip ./scripts/xf-addon 2.3.12 8.3 rebuild
```

### Use an external add-on path

You can keep your add-on in another repo:

```bash
ADDON_ID=Vendor/AddOn \
ADDON_SOURCE=/Users/you/code/my-addon/src/addons/Vendor/AddOn \
./scripts/xf-up 2.3.12 8.3
```

You can also point `ADDON_SOURCE` at a project root that contains:

```text
src/addons/Vendor/AddOn/addon.json
```

or:

```text
addons/Vendor/AddOn/addon.json
```

---

## Install every add-on in `addons/` (bulk, dependency-ordered)

Drop any number of add-on release ZIPs into `addons/` and install them all in one run:

```bash
ADDONS_ALL=1 ./scripts/xf-up 2.3.12 8.3
```

For each ZIP the lab copies its `upload/` files into the webroot, discovers the add-on
ID(s) from `upload/src/addons/.../addon.json`, then **installs them in dependency order**.
The order is computed from each add-on's `addon.json` `require` block (a topological
sort), so a base library is always installed before the add-ons that depend on it. ZIP
**filenames are irrelevant** to ordering — only the add-on IDs and their declared
requirements matter. If two add-ons form a dependency cycle, the run fails loudly rather
than guessing.

`SV/StandardLib` is treated as a shared base library and is forced ahead of **every**
other `SV/*` add-on, even ones that do not list it in `require` (for example
`SV/RedisCache`, which only requires XF/PHP). To extend or override this for other vendor
libraries, set `ADDON_BASE_LIBS` to a space- or comma-separated list of add-on IDs, e.g.
`ADDON_BASE_LIBS="SV/StandardLib Other/CoreLib"`.

`ADDONS_ALL=1` ignores `ADDON_ID`. Add-ons whose requirements are not met by the chosen
XenForo/PHP version (for example an add-on needing XF 2.3 on a 2.1 instance) will report
an install error — which is the point of a compatibility lab.

## Optional Elasticsearch and Redis services

Some add-ons need a backing service. These are **opt-in** containers on the same Compose
network; they publish **no host ports** (PHP reaches them by service name, exactly like
`db`), so multiple instances never collide.

```bash
# XenForo Enhanced Search (XFES) needs Elasticsearch; SV/RedisCache needs Redis.
ELASTICSEARCH=1 REDIS=1 ADDONS_ALL=1 ./scripts/xf-up 2.3.12 8.3
```

**Elasticsearch (XFES).** After the instance is up, configure it in the Admin CP under
**Setup → Enhanced Search**: host `elasticsearch`, port `9200`, **uncheck "Use HTTPS
connection"** (the container runs plain HTTP — security is disabled), leave username/password
blank. Click **Test settings**, confirm to enable, then rebuild the search index — either via
the Admin CP, or from the CLI:

```bash
./scripts/xf-run 2.3.12 8.3 php cmd.php xf-rebuild:search
```

The container runs single-node with security disabled and a 512 MB heap (override with
`ES_IMAGE` / `ES_JAVA_OPTS`). Verified working end-to-end: new threads live-index to
Elasticsearch and front-end search returns them relevance-ordered through `elasticsearch:9200`.

**Redis.** The `phpredis` PECL extension is **always** built into the PHP image (pinned to
`redis-5.3.7` on PHP < 7.4, latest otherwise), so it is present even without `REDIS=1`.
`REDIS=1` adds a `redis` container reachable from PHP at host `redis`, port `6379`.

When `REDIS=1` **and** `SV/RedisCache` is among the installed add-ons (e.g. via
`ADDONS_ALL=1`), the lab **automatically appends** this config to
`instances/<name>/webroot/src/config.php` (taken from the add-on's own
`config-examples/config.redis-example-minimal.php`, with `server` pointed at the `redis`
container):

```php
$config['cache']['sessions'] = true;
$config['cache']['enabled']  = true;
$config['cache']['provider'] = \SV\RedisCache\Redis::class;
$config['cache']['config']   = ['server' => 'redis', 'port' => 6379];
```

It is appended only once (idempotent), and **only** when both conditions hold — pointing
`provider` at a class from an add-on that is not installed, or at a Redis server that is
not running, would fatal XenForo. Without `REDIS=1`, or without `SV/RedisCache`, nothing is
written and you can add the block above yourself.

---

## Public HTTPS tunnel with ngrok

Some add-ons need the XenForo install to be reachable from an external backend, webhook, or API service.

Use public mode:

```bash
PUBLIC=ngrok \
NGROK_AUTHTOKEN='YOUR_NGROK_TOKEN' \
ADDON_ID=Vendor/AddOn \
./scripts/xf-up 2.3.12 8.3
```

The script will:

1. start Docker;
2. start ngrok;
3. read the public HTTPS URL from the local ngrok agent API;
4. install XenForo using that public URL as the board URL.

Example output:

```text
ngrok public URL: https://example.ngrok-free.app

Instance ready.

  Forum:  https://example.ngrok-free.app/
  Admin:  https://example.ngrok-free.app/admin.php
  Basic auth: zee / generated-password
  User:   admin
  Pass:   Admin123Pass
```

### ngrok authtoken storage

On first public run, pass:

```bash
NGROK_AUTHTOKEN='YOUR_NGROK_TOKEN'
```

The script stores it under the generated instance folder so you do not need to pass it every time for that same instance.

Generated instance folders are ignored by git.

---

## Basic Auth for public tunnels

Public ngrok mode enables HTTP Basic Auth by default.

That is intentional because public XenForo test installs should not be left openly accessible.

Default credentials are:

```text
username: zee
password: generated per instance
```

You can set your own:

```bash
PUBLIC=ngrok \
NGROK_AUTH_USER=dev \
NGROK_AUTH_PASS='SomeLongPassword123' \
ADDON_ID=Vendor/AddOn \
./scripts/xf-up 2.3.12 8.3
```

### Disable Basic Auth

If your external backend must call XenForo and cannot send Basic Auth credentials, disable it:

```bash
PUBLIC=ngrok \
NGROK_AUTH=0 \
ADDON_ID=Vendor/AddOn \
./scripts/xf-up 2.3.12 8.3
```

Only do this while actively testing. Stop or destroy the instance afterwards.

---

## Use a reserved/static ngrok URL

If your ngrok account has a reserved/static domain, pass it with `NGROK_URL`:

```bash
PUBLIC=ngrok \
NGROK_URL=https://your-static-domain.ngrok-free.app \
ADDON_ID=Vendor/AddOn \
./scripts/xf-up 2.3.12 8.3
```

This is useful when your backend stores or validates the board URL.

---

## Suggested compatibility matrix

Use the combinations that make sense for your add-on and the XenForo versions you support.

Common examples:

```bash
./scripts/xf-up 2.1.15 7.2
./scripts/xf-up 2.1.15 7.4

./scripts/xf-up 2.2.19 7.4
./scripts/xf-up 2.2.19 8.0
./scripts/xf-up 2.2.19 8.1
./scripts/xf-up 2.2.19 8.2

./scripts/xf-up 2.3.12 8.1
./scripts/xf-up 2.3.12 8.2
./scripts/xf-up 2.3.12 8.3
./scripts/xf-up 2.3.12 8.4
```

Older PHP images may require archived Debian package repositories. The included Dockerfile handles the common PHP 7.2 / Debian Buster case.

---

## Helper commands

### List generated instances

```bash
./scripts/xf-list
```

### Open a shell inside the PHP container

```bash
./scripts/xf-shell 2.3.12 8.3
```

### Run a command inside the PHP container

```bash
./scripts/xf-run 2.3.12 8.3 -- php -v
```

```bash
./scripts/xf-run 2.3.12 8.3 -- php cmd.php list
```

### Run add-on commands

```bash
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 install
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 upgrade
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 rebuild
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 uninstall
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 enable
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 disable
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 validate-json
ADDON_ID=Vendor/AddOn ./scripts/xf-addon 2.3.12 8.3 sync-json
```

### Stop an instance but keep files/database

```bash
./scripts/xf-down 2.3.12 8.3
```

### Destroy an instance completely

```bash
./scripts/xf-down 2.3.12 8.3 --destroy
```

This deletes:

```text
instances/xf-2.3.12-php-8.3/
```

including the database, extracted XenForo files, ngrok token copy, logs, and generated config.

---

## Environment variables

| Variable | Purpose |
|---|---|
| `PORT` | Override the local HTTP port. |
| `DB_IMAGE` | Database container image. Default: `mariadb:10.6`. For example `mariadb:10.11` or `mysql:8.0`. |
| `DB_PING_BIN` | Override the healthcheck/readiness ping binary. Auto-selected from `DB_IMAGE` (`mariadb-admin` for MariaDB, `mysqladmin` for MySQL). |
| `SERVICE` | Compose service that `xf-shell`/`xf-run` target. Default: `php`. Use `db` for the database container. |
| `ADDON_ID` | XenForo add-on ID, for example `Vendor/AddOn`. |
| `ADDON_SOURCE` | Optional external path to the add-on source. |
| `ADDONS_ALL=1` | Import and install **every** `.zip` in `addons/`, ordered by each add-on's `addon.json` `require` (e.g. `SV/StandardLib` installs before SV add-ons that depend on it). Overrides `ADDON_ID`. |
| `ELASTICSEARCH=1` | Add an Elasticsearch container for XenForo Enhanced Search (XFES). `ES=1` also works. |
| `ES_IMAGE` | Elasticsearch image. Default: `docker.elastic.co/elasticsearch/elasticsearch:8.15.3`. |
| `ES_JAVA_OPTS` | Elasticsearch JVM heap. Default: `-Xms512m -Xmx512m`. |
| `REDIS=1` | Add a Redis container (for the `SV/RedisCache` add-on). The `phpredis` extension is always built into the PHP image. |
| `REDIS_IMAGE` | Redis image. Default: `redis:8-alpine`. |
| `PUBLIC=ngrok` | Enable public ngrok tunnel mode. |
| `NGROK=1` | Alternative way to enable ngrok mode. |
| `NGROK_AUTHTOKEN` | ngrok authtoken. Required on first public run for an instance. |
| `NGROK_AUTH` | Set to `0` to disable Basic Auth in public mode. |
| `NGROK_AUTH_USER` | Basic Auth username. Default: `zee`. |
| `NGROK_AUTH_PASS` | Basic Auth password. Default: generated per instance. |
| `NGROK_URL` | Optional reserved/static ngrok URL. |
| `NGROK_API_PORT` | Override local ngrok agent API port. |
| `LAB_URL` | Manually set XenForo board URL. Mostly useful for custom tunnel/proxy setups. |

---

## Troubleshooting

### `Missing XenForo archive`

The script could not find the expected ZIP.

Example:

```bash
./scripts/xf-up 2.3.12 8.3
```

requires:

```text
archives/xenforo-2.3.12.zip
```

### `Missing command: docker`

Docker is not installed or not available in your shell path. Start Docker Desktop and retry.

### `Missing command: ngrok`

You used `PUBLIC=ngrok` but ngrok is not installed.

On macOS:

```bash
brew install ngrok
```

### Installer fails and then add-on install complains about missing tables

If you see something like:

```text
Table 'xenforo.xf_data_registry' doesn't exist
```

the XenForo install did not complete correctly. Destroy the half-created instance and rerun:

```bash
./scripts/xf-down 2.3.12 8.3 --destroy
./scripts/xf-up 2.3.12 8.3
```

### ngrok URL changed but XenForo still uses the old board URL

If an instance was already installed, XenForo may still store the old board URL.

For a clean public test:

```bash
./scripts/xf-down 2.3.12 8.3 --destroy

PUBLIC=ngrok \
ADDON_ID=Vendor/AddOn \
./scripts/xf-up 2.3.12 8.3
```

### PHP 7.2 build issues

Very old PHP Docker images are based on old Debian releases. This project rewrites the Debian Buster package sources to archived Debian mirrors before running `apt-get update`.

If your platform still cannot build PHP 7.2, try PHP 7.4 first to confirm Docker itself is working:

```bash
./scripts/xf-up 2.1.15 7.4
```

---

## Built while testing AI Rules Moderation

This lab was originally built while testing real-world XenForo add-on compatibility across old XenForo branches, old PHP runtimes, modern PHP runtimes, and public callback environments.

The original use case was **AI Rules Moderation**, a commercial XenForo add-on for conservative AI-assisted moderation triage.

AI Rules Moderation is designed to help forum owners check new and edited content against administrator-defined rules, send questionable content into the normal XenForo moderation workflow, and reduce repetitive manual review without replacing human moderators.

You can find AI Rules Moderation on XenForo Resource Manager:

```text
https://xenforo.com/community/resources/ai-rules-moderation.10496/
```

This repository does not include AI Rules Moderation files. Bring your own licensed add-on files and mount them using `ADDON_ID` and `ADDON_SOURCE`.

---

## Licence

This project is released under the MIT License. See [`LICENSE`](LICENSE).

This licence applies only to the scripts, templates, documentation, and supporting files in this repository.

It does **not** grant any rights to XenForo itself. XenForo is commercial software owned by XenForo Limited, and you must supply your own licensed XenForo download archives.

It also does **not** grant any rights to third-party or commercial XenForo add-ons you choose to mount, install, or test with this lab. You are responsible for complying with XenForo's licence terms and the licence terms of any add-ons you use.