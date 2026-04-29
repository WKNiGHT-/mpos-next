# MPOS — Docker Dev Environment

A self-contained docker stack for working on the MPOS modernization. Runs **PHP 8.3 + Apache, MySQL 8, memcached, phpMyAdmin** locally with no host-side PHP/MySQL needed.

This is a **development** environment — it ships with sensible defaults that are not safe for the public internet. Don't deploy it as-is.

---

## Prerequisites

- Docker Desktop (or Docker Engine 20.10+) and `docker compose`
- `make` (the included `Makefile` is a thin wrapper around `docker compose`)
- ~2 GB free disk for images + the MySQL volume

That's it. PHP, Composer, MySQL, and memcached all live inside the containers.

---

## First-time startup (3 commands)

```bash
cp .env.example .env              # edit if you have port collisions
make up                           # builds images, starts the stack (3–5 min first time)
make bootstrap-config             # generates include/config/global.inc.php
```

Then verify everything is green:

```bash
make health
```

You should see all five checks pass:

```
[ OK ]  php               PHP 8.3.x (cli)
[ OK ]  ext.mysqli        loaded
[ OK ]  ext.memcached     loaded
[ OK ]  mysql             connected to mysql:3306/mpos (server 8.0.x)
[ OK ]  memcached         connected to memcached:11211 (pid …, version 1.6.x)
healthy
```

---

## Default URLs and credentials

| Service        | URL                      | Login                          |
| -------------- | ------------------------ | ------------------------------ |
| MPOS web       | http://localhost:8080    | _MPOS app account_             |
| phpMyAdmin     | http://localhost:8081    | `root` / `mpos_root`           |
| MySQL (host)   | `localhost:3306`         | `mpos` / `mpos` (db: `mpos`)   |
| Memcached      | `localhost:11211`        | _no auth_                      |
| Healthcheck    | http://localhost:8080/healthcheck.php | _none_           |

Override any of these by editing `.env` before `make up`.

> **Schema** is loaded automatically on the **first** MySQL boot via the `sql/` directory mounted into `/docker-entrypoint-initdb.d`. To re-run migrations, see _Reset_ below.

---

## Useful Make targets

```bash
make help               # list every target with description
make up                 # start the stack
make down               # stop the stack (data preserved)
make restart            # down + up
make ps                 # show container status
make logs               # tail logs from all services
make logs-web           # tail just the web container
make logs-db            # tail just MySQL
make health             # run the healthcheck inside the web container
make bootstrap-config   # generate global.inc.php from the .dist file
make shell              # bash inside the web container
make mysql              # mysql client inside the mysql container
make php-lint           # syntax-lint every .php file (skips Smarty libs)
make composer ARGS="…"  # run composer in the web container
make cron-statistics    # run cronjobs/statistics.php inside the web container
make clean              # stop and remove orphan containers
make nuke               # stop and DELETE the MySQL volume (full reset)
```

---

## Troubleshooting

### `make up` fails on the build step

- **Architecture issues on Apple Silicon**: the base images (`php:8.3-apache-bookworm`, `mysql:8.0`, `memcached:1.6-alpine`) all have `arm64` variants — Docker should pick the right one automatically. If you see `no matching manifest`, update Docker Desktop.
- **Slow PECL install**: `pecl install memcached` compiles from source; first build can take 2–4 min. Subsequent rebuilds are cached.

### `make health` reports MySQL `connect failed`

The MySQL container takes a few seconds to finish initializing on its very first boot (it's loading the schema files in `sql/`). Wait ~10 s and retry. If it persists:

```bash
make logs-db | tail -50          # look for MySQL errors
docker compose ps                # confirm mysql is "healthy"
```

If the schema files have an error, MySQL leaves the data dir in a half-init state. Use `make nuke` and try again.

### `make health` reports memcached `could not reach`

Most often the memcached service didn't start — `make logs` will show why. The `memcached:1.6-alpine` image is tiny so failures are usually port-related (something on the host is bound to 11211).

### Port collision (host already using 8080 / 3306 / 11211 / 8081)

Edit `.env` and change `WEB_PORT`, `MYSQL_PORT`, `MEMCACHED_PORT`, or `PMA_PORT`, then `make restart`.

### "global.inc.php already exists" — how to regenerate

The bootstrap refuses to clobber by default. To force-regen (with a timestamped backup of the old one):

```bash
docker compose exec web php scripts/bootstrap-config.php --force
```

### Web returns 500 with no error visible

```bash
make logs-web                    # Apache + PHP error log lives here
```

`docker/php.ini` sets `display_errors=On` and routes `error_log` to stderr, so PHP errors should appear inline in `make logs-web`.

### "Database version mismatch (Installed: X.Y.Z, Current: 1.0.3)" popup on every page

The application compares the `DB_VERSION` row in the `settings` table against the constant defined in `include/version.inc.php`. The current `sql/000_base_structure.sql` correctly seeds `DB_VERSION = '1.0.3'` on a **fresh** MySQL volume, so a from-scratch `make up` lands at the right version automatically.

If you see a mismatch popup with `Installed: 0.0.1` (or any other older version), your MySQL named volume (`mpos_mysql_data`) was first initialized **before** the upstream sync that updated the base schema. MySQL only runs `/docker-entrypoint-initdb.d/` on the **first** boot of an empty volume — every subsequent `make up` reuses whatever DB_VERSION value the original init wrote.

Fix: rebuild from a clean volume.

```bash
make nuke                       # stops the stack and DELETES mpos_mysql_data
make up                         # fresh MySQL re-runs sql/000_base_structure.sql
make bootstrap-config --force   # regenerate include/config/global.inc.php
make health
```

After that, `SELECT value FROM settings WHERE name = 'DB_VERSION';` should return `1.0.3` and the popup is gone.

### Smarty cache/compile permission errors

```bash
make shell
chown -R www-data:www-data templates/cache templates/compile
```

(Upstream relocated templates from `public/templates/` to top-level `templates/`. The bind mount means file ownership reflects your host; Apache runs as `www-data` inside the container.)

### Healthcheck endpoint is reachable from the public internet

It's intended for local dev. If you publish your dev box, either:

- Delete `public/healthcheck.php`, or
- Add a `Require ip 127.0.0.1` block in `docker/apache-vhost.conf` for that file.

---

## Reset

```bash
make nuke                        # stop everything, delete the MySQL volume
make up                          # fresh stack — schema re-runs from sql/*.sql
make bootstrap-config            # regenerate config (old one is gitignored)
make health
```

`global.inc.php` is **not** deleted by `make nuke` (it lives on the host, not in a docker volume). Delete it by hand if you want a true clean slate, or run `make bootstrap-config ARGS=--force` to regenerate it (the old file gets a `.bak.<timestamp>` neighbor).

**When to do this:** any time the contents of `sql/000_base_structure.sql` change. MySQL's `/docker-entrypoint-initdb.d/` only fires on the first boot of an empty volume, so a stale dev volume will silently keep whatever schema it was initialized with — most commonly seen as a "Database version mismatch" popup if `DB_VERSION` has bumped since first init.

---

## What this environment does *not* do (yet)

- No coin daemon — you'll need to add a `litecoind`/`bitcoind` testnet container or an external RPC endpoint, then point `$config['wallet']` at it. (Phase 5 will add a daemon container example.)
- No HTTPS — Apache serves plain HTTP on `:8080`. For local dev that's fine.
- No PHP 8.3 syntax fixes have been applied yet — bringing the web container up against legacy code will surface deprecation warnings until Phase 1 is complete.

See `MEMORY.md` (in your Claude memory dir) and the in-conversation task list for the full modernization roadmap.
