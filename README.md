[![Build Status](https://travis-ci.org/MPOS/php-mpos.png?branch=master)](https://travis-ci.org/MPOS/php-mpos) [![Code Climate](https://codeclimate.com/github/MPOS/php-mpos/badges/gpa.svg)](https://codeclimate.com/github/MPOS/php-mpos) [![Code Coverage](https://scrutinizer-ci.com/g/MPOS/php-mpos/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/MPOS/php-mpos/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/MPOS/php-mpos/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/MPOS/php-mpos/?branch=master) master<br />
[![Build Status](https://travis-ci.org/MPOS/php-mpos.png?branch=development)](https://travis-ci.org/MPOS/php-mpos) [![Code Coverage](https://scrutinizer-ci.com/g/MPOS/php-mpos/badges/coverage.png?b=development)](https://scrutinizer-ci.com/g/MPOS/php-mpos/?branch=development) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/MPOS/php-mpos/badges/quality-score.png?b=development)](https://scrutinizer-ci.com/g/MPOS/php-mpos/?branch=development)  development

Modernized fork (PHP 8.3 + Docker)
==================================

This fork brings MPOS up to a modern PHP/MySQL stack and ships with a self-contained Docker dev environment. The upstream project targeted PHP 5.4 and assumed a bare-metal Apache install; that all still works, but the fast path is now Docker.

**Quickstart (3 commands):**

```bash
cp .env.example .env
make up
make bootstrap-config
```

Then check everything is healthy:

```bash
make health
```

You should see five green checks (PHP 8.3, mysqli, memcached, MySQL connection, memcached connection).

**URLs and dev credentials** (defaults — override in `.env` if you have port conflicts):

| Service        | URL                      | Login                          |
| -------------- | ------------------------ | ------------------------------ |
| Web UI         | http://localhost:8080    | _MPOS app account_             |
| phpMyAdmin     | http://localhost:8081    | `root` / `mpos_root`           |
| Healthcheck    | http://localhost:8080/healthcheck.php | _none_           |
| MySQL (host)   | `localhost:3306`         | `mpos` / `mpos` (db: `mpos`)   |
| Memcached      | `localhost:11211`        | _no auth_                      |

**Don't deploy these defaults to the public internet** — they're for local dev only. For full Docker docs (troubleshooting, reset, useful Make targets), see [`README-DOCKER.md`](README-DOCKER.md).

Modernized architecture
-----------------------

Four containers wired up by `docker-compose.yml`:

| Service       | Image                       | Role                                                                |
| ------------- | --------------------------- | ------------------------------------------------------------------- |
| `web`         | PHP 8.3 + Apache (custom)   | Application runtime (DocumentRoot = `public/`, mod_rewrite, mod_php) |
| `mysql`       | `mysql:8.0`                 | Database (utf8mb4 server-side; schema auto-loads `sql/000_base_structure.sql` on first boot) |
| `memcached`   | `memcached:1.6-alpine`      | Cache layer (consumed by `StatsCache`, `mc_antidos` rate limiter)   |
| `phpmyadmin`  | `phpmyadmin:5`              | DB browser, dev convenience                                         |

**Composer** (`composer.json` / `vendor/`) replaces several bundled libraries with managed packages:

- `smarty/smarty:^5.0` — modern Smarty 5 instantiated via the namespaced `\Smarty\Smarty` class. The legacy bundled Smarty 3.1.16 stays under `include/smarty/` as a safety fallback (`include/smarty.inc.php` branches on `class_exists('\Smarty\Smarty')`); only one is loaded per request, the namespacing keeps them from colliding.
- `michelf/php-markdown:^2.0` — same author and namespace (`\Michelf\Markdown`) as the bundled file it replaced.
- `katzgrau/klogger:^1.2` — PSR-3 rewrite of the original logger. `include/classes/klogger_compat.class.php` re-exposes the legacy v0.2 API (`KLogger::instance(...)`, integer level constants, `logInfo()`/`logError()`) on top of it, so the ~195 callsites across cronjobs and controllers continue to work unchanged.
- `google/recaptcha:^1.1` — already on Composer upstream; preserved.

**Cache layer (Memcached):** the legacy Windows-only `Memcache` shim was deleted; the codebase now uses native PHP `ext-memcached` everywhere. SASL is gated behind `$config['memcache']['sasl']`.

**Config bootstrap:** `scripts/bootstrap-config.php` reads `include/config/global.inc.dist.php`, swaps in Docker service hostnames (`mysql`, `memcached`) plus DB credentials from the container env (`MPOS_DB_HOST`/`USER`/`PASS`/`NAME`), generates fresh random `SALT` and `SALTY` values, lints the result, and writes `include/config/global.inc.php` (which is gitignored). Idempotent — re-runs are no-ops unless `--force`'d.

Developer notes
---------------

```bash
make help                  # all available targets
make logs                  # tail logs from all services
make logs-web              # web container only
make logs-db               # mysql only
make shell                 # bash inside the web container
make mysql                 # mysql client inside the mysql container
make php-lint              # syntax-lint every .php file (skips vendored libs)
make composer ARGS="…"     # run composer in the web container
make composer-install      # install/refresh dev tools (rector, phpcs, phpstan)
make rector-dry            # inspect what Rector would change
make phpcs                 # PHPCompatibility scan against PHP 8.3
make cron-statistics       # run cronjobs/statistics.php inside the web container
make cron-tickerupdate     # …or any other cron
make nuke                  # stop and DELETE all data (mysql volume too)
```

**Where logs live:**

- Application + cron logs: `logs/<cron_name>/log_YYYY-MM-DD.txt` (KLogger, gitignored)
- Apache + PHP errors: visible via `make logs-web` (routed to stderr by `docker/php.ini`)
- MySQL: `make logs-db`

**Running cronjobs by hand:** `make cron-<name>` (e.g. `make cron-statistics`). Cronjobs run inside the web container and use the bootstrapped `include/config/global.inc.php`.

**Linting & static analysis:**

- `make php-lint` — sequential `php -l` over every project file; reports each fatal with file + line.
- `make rector-dry` — inspect Rector's PHP-8.3-upgrade suggestions without writing.
- `make phpcs` — PHPCompatibility scan against PHP 8.3 target.
- `make phpstan` — level-0 baseline static analysis.

Upgrade notes (legacy → modernized)
-----------------------------------

If you're coming from upstream MPOS or an older fork:

| Area | Change |
| --- | --- |
| **PHP** | Bumped from 5.4 → **8.3**. All parse-level fatals (curly-brace string offsets, `create_function`, PHP 4-style ctors, duplicate `static`) and most runtime deprecations (count(null), implicit-nullable params, dynamic property writes, return-type mismatches) have been mopped up. |
| **Cache** | The legacy `Memcache` (ext-memcache) shim is **gone**. Runtime uses native `ext-memcached` on every platform. |
| **Smarty** | Active runtime is **Smarty 5** via Composer. Bundled Smarty 3.1.16 still on disk at `include/smarty/` as a fallback if `vendor/` is missing. **Templates were not modified** — the upgrade is invisible to template authors. Custom v3 plugins (`relative_date`, `seconds_to_hhmmss`, `seconds_to_words`) are auto-registered for v5; PHP built-ins used as modifiers (`file_exists`, `count`, `strlen`, `round`, `explode`) are explicitly registered. |
| **Markdown** | Bundled `Michelf/Markdown.php` → `michelf/php-markdown:^2.0` via Composer. Same namespace, same `\Michelf\Markdown::defaultTransform()` API — controllers untouched. |
| **Logger** | Bundled v0.2 `KLogger.php` → `katzgrau/klogger:^1.2` via Composer. The shim at `include/classes/klogger_compat.class.php` keeps the v0.2 API (`KLogger::instance(...)`, `logInfo()`, integer level constants) callable across the 195 existing call sites. |
| **DB** | Connection setup (`include/database.inc.php`) hardened: connect failures are now caught and rendered as a clean message instead of leaking a PHP backtrace; `SET NAMES utf8mb4` is forced on the active connection; the read-only check is null-safe. |
| **Templates** | Not touched. Phase 3B added controller-side `$_POST` defaults for the registration form to silence Smarty 5 "Undefined array key" warnings without editing `.tpl` files. |

Troubleshooting
---------------

For Docker-stack issues (build failures, MySQL not ready, port collisions, permission errors on `templates_c/`, healthcheck failures), see [`README-DOCKER.md`](README-DOCKER.md). The same file documents `make nuke` for a full reset.

If pages 500 with no body content: `make logs-web | tail -50` — `display_errors` is **off** in the dev image (so deprecation noise doesn't poison HTTP responses); errors are routed to stderr instead.

If `make bootstrap-config` says config already exists, force-regen with:

```bash
docker compose exec web php scripts/bootstrap-config.php --force
```

(The old config gets backed up next to it as `global.inc.php.bak.<timestamp>`.)

---

Description
===========

MPOS is a web based Mining Portal for various crypto currencies. It was originally created by [TheSerapher](https://github.com/TheSerapher) and has hence grown quite large. It's now used by many pools out there and is a good starting point to learn more about mining and running pools in general. There is no active development done on the project by the orignal developers but we still merge PRs!

Donations
=========

Donations to this project are going directly to [TheSerapher](https://github.com/TheSerapher), the original author of this project:

* BTC address: `1HuYK6WPU8o3yWCrAaADDZPRpL5QiXitfv`
* LTC address: `Lge95QR2frp9y1wJufjUPCycVsg5gLJPW8`

Website Footer
==============

When you decide to use `MPOS` please be so kind and leave the footer intact. You are not the author of the software and should honor those that have worked on it. Keeping the footer intact helps spreading the word. Leaving the donation address untouched allows miners to donate to the author.

Pools running MPOS
==================

You can find a list of active pools [here](https://github.com/TheSerapher/php-mpos/wiki/Pools).

Requirements
============

This setup has been tested on Ubuntu 12.04, Ubuntu 13.04 and CentOS.
It should also work on any related distribution (RHEL, Debian).

Be aware that `MPOS` is **only** for pooled mining. Solo Mining is not
supported. They will never match an upstream share, solo miners do not create
any shares, only blocks. Expect weird behavior if trying to mix them. See #299
for full information.

* 64-bit system
 * Otherwise some coins will display wrong network hashrates
* Apache2
 * libapache2-mod-php5
* PHP 5.4+
 * php5-json
 * php5-mysqlnd
 * php5-memcached
 * php5-curl
* MySQL Server
 * mysql-server
* memcached
* stratum-mining
* litecoind

Features
========

The following feature have been implemented so far:

* Fully re-written GUI with [Smarty][2] templates
 * Full file based template support
* VARDIFF Support
* Reward Systems
 * Propotional, PPS and PPLNS
* New Theme
 * Live Dashboard
 * AJAX Support
 * Overhauled API
 * Bootstrap
* Web User accounts
 * Re-Captcha protected registration form
* Worker accounts
 * Worker activity
 * Worker hashrates
* Pool statistics
* Block statistics
* Pool donations, bonuses, fees and block bonuses
* Manual and auto payout
* Transaction list
* Admin Panel
 * Cron Monitoring Overview
 * User Listing including statistics
 * Wallet information
 * User Transactions
 * News Posts
 * Pool Settings
 * Pool Workers
 * User Reports
* Notification system
 * IDLE Workers
 * New blocks found in pool
 * Auto Payout
 * Manual Payout
* User-to-user Invitation System
* Support for various coins via coin class and config
 * All scrypt coins
 * All sha256d coins
 * All x11 coins
 * Others may be supported by creating a custom coin class 

Installation
============

Please take a look at the [Quick Start Guide](https://github.com/TheSerapher/php-mpos/wiki/Quick-Start-Guide). This will give you an idea how to setup `MPOS`. Please be aware that the `master` branch is our currently considered stable system while `development` is used as a test bed for all upcoming changes for `master`. If you wish to run a stable, well tested system ensure you run `git checkout master`. If you decide to stick to the `development` branch with bleeding edge code and potential bugs, just `git clone` the project.

Customization
=============

This project was meant to allow users to easily customize the system and templates. Hence no upstream framework was used to keep it as simple as possible.
If you are just using the system, there will be no need to adjust anything. Things will work out of the box! But if you plan on creating
your own theme, things are pretty easy:

* Create a new theme folder in `templates/`
* Create a new site_assets folder in `public/site_assets`
* Create your own complete custom template or copy from an existing one
* Change your theme in the `Admin Panel` and point it to the newly created folder

The good thing with this approach: You can keep the backend code updated! Since your new theme will never conflict with existing themes, a simple git pull will
keep your installation updated. You decide which new feature you'd like to integrate on your own theme. Bugfixes to the code will work out of the box!

Other customizations are also possible but will require merging changes together. Usually users would not need to change the backend code unless they wish to work
on non-existing features in `MPOS`. For the vast majority, adjusting themes should be enough to highlight your pool from others.

In all that, I humbly ask to keep the `MPOS` author reference and Github URL intact.

Contributing
============

You can contribute to this project in different ways:

* Report outstanding issues and bugs by creating an [Issue][1]
* Fork the project, create a branch and file a pull request **against development** to improve the code itself

Contact
=======

This product is not actively developed anymore. For setup and installation support, please find help in other channels.
This projects issue tracker is used for bugs and issues with the core code, not for general help in setting up and running 
pool.

Team Members
============

Author and Project Owner: [TheSerapher](https://github.com/TheSerapher) aka Sebastian Grewe

Past developers that helped on MPOS in the early days:

* [nrpatten](https://github.com/nrpatten)
* [Aim](https://github.com/fspijkerman)
* [raistlinthewiz](https://github.com/raistlinthewiz)
* [xisi](https://github.com/xisi)
* [nutnut](https://github.com/nutnut)
* [obigal](https://github.com/obigal)
* [iAmShorty](https://github.com/iAmShorty)
* [rog1121](https://github.com/rog1121)
* [neozonz](https://github.com/neozonz)

License and Author
==================

Copyright 2012, Sebastian Grewe

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.


  [1]: https://github.com/TheSerapher/php-mpos/issues "Issue"
  [2]: http://www.smarty.net/docs/en/ "Smarty"
