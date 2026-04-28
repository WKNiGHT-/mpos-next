Modernization track (2026)
--------------------------

Brings the codebase up to PHP 8.3 + Docker without touching templates,
core business logic, or the schema. Every commit on top of upstream's
last `next` (`236cccd5`) is in this track.

* **Phase 0 — Docker development environment**
  Self-contained PHP 8.3-Apache + MySQL 8 + memcached + phpMyAdmin
  stack via `docker-compose.yml`. `Makefile` wraps the common
  operations (`up`, `down`, `health`, `php-lint`, `cron-<name>`, `nuke`,
  …). `scripts/bootstrap-config.php` generates `global.inc.php` from
  the dist file with random `SALT`/`SALTY` and Docker hostnames.
  `public/healthcheck.php` is a dual-mode (HTTP/CLI) endpoint that
  verifies PHP, ext.mysqli, ext.memcached, MySQL connectivity, and
  memcached connectivity. See `README-DOCKER.md`.

* **Phase 1 — PHP 8 compatibility (sub-phases A–G)**
  - 1A: Composer dev tooling (rector, phpcs + phpcompatibility,
    phpstan).
  - 1B: Parse-level fatals (`create_function`, curly-brace string
    offsets in Markdown.php / SwiftMailer, PHP-4 ctors in xmlrpc/
    jsonrpc, duplicate `static` in tools.class.php, typed
    `Exception::$line` in bundled Smarty 3, `#[\AllowDynamicProperties]`
    on `Base`).
  - 1C: Runtime stabilization (count-null guard in `index.php`;
    `display_errors=Off` to keep deprecation noise out of HTTP
    responses; pulled `smarty/smarty 4` out of vendor pending Phase 3A).
  - 1D: Eliminated 8× per-page `monitoring.class.php:60` "array offset
    on null" via `?? null`.
  - 1E: Guarded `session_regenerate_id()` after `session_destroy()` in
    `user.class.php`.
  - 1F: Removed unreachable defaults on 7 optional-before-required
    parameters in `share`/`statistics`/`payout`/`roundstats`.
  - 1G: `#[\ReturnTypeWillChange]` on `mysqlims` overrides;
    `#[\AllowDynamicProperties]` on `Logger`/`Debug`/`StatsCache`/
    `BitcoinWrapper`; `false → []` in `global.inc.dist.php`;
    `HTTP_USER_AGENT` default for the bundled Mobile_Detect's
    `preg_match` deluge.

* **Phase 2 — Memcache → Memcached migration**
  Deleted the legacy Windows-only ext-memcache shim
  (`include/classes/memcached.class.php`); runtime uses native PHP
  `ext-memcached` on every platform.

* **Phase 3 — Composer dependency migration (incremental)**
  Bundled libs replaced with Composer-managed equivalents, one at a
  time, verifying after each:
  - Smarty 5 (`smarty/smarty:^5.0`) wired alongside the legacy bundled
    V3 in `include/smarty.inc.php` (Phase 3A); custom V3 plugins
    auto-registered for V5; PHP built-in modifiers explicitly registered.
  - Smarty 5 page-render stabilization (Phase 3B): controller-side
    `$_POST` defaults for the registration form so the template
    renders without "Undefined array key" warnings, no `.tpl` edits.
  - Markdown (`michelf/php-markdown:^2.0`) — same namespace, drop-in.
  - KLogger (`katzgrau/klogger:^1.2`) — wrapped in
    `klogger_compat.class.php` so the legacy v0.2 API still works.

* **Phase 5 — DB and config polish**
  `include/database.inc.php` hardened: try/catch around mysqlims
  connection (no more PHP backtrace leaking on DB failure), explicit
  `SET NAMES 'utf8mb4'`, null-safe read-only check, clearer error
  message that names host:port without exposing creds.

Architecture deltas summary
~~~~~~~~~~~~~~~~~~~~~~~~~~~

| Surface | Before | After |
| ------- | ------ | ----- |
| PHP target | 5.4+, libapache2-mod-php5 | **8.3**, official `php:8.3-apache` |
| Cache | bundled `Memcache` shim (ext-memcache only on Windows) | native `ext-memcached` everywhere |
| Smarty | bundled v3.1.16 only | **v5.8 via Composer**, v3 still on disk as fallback |
| Markdown | bundled Michelf v1.x (87 KB) | `michelf/php-markdown:^2` via Composer |
| Logger | bundled v0.2 `KLogger.php` (12 KB) | `katzgrau/klogger:^1.2` + PSR-3, behind a v0.2-API shim |
| Templates | unchanged | unchanged |
| Schema | unchanged | unchanged |

For a full per-commit log, see `git log` on the modernization branch.

1.0.5 (XXX XXth XXXX)
---------------------

* Fixed worker name scaling issues on mobile devices (Thanks @nrpatten)
* Fixed user information table formatting (Thanks @pokari1986)
* Fixed empty auto-payout threshold value for accounts page
* Removed config disable check popup for admins on all pages
* Added blockchain download status for admin feedback (admin setup check)
* Added peer state to wallet info state if no peers are connected

1.0.4 (Jun 19th 2015)
---------------------

* Honor anonymous attribute when sending block finder mails
* Display admin warning if no transfer fees are set
* Moved admin_checks.php into the admin panel/system/setup
 * Checks are now loaded individually from pages/admin/checks

1.0.3 (Apr 29th 2015)
---------------------

* HOTFIX: Database upgrade from `1.0.0` to `1.0.1` did not work as
  intended

1.0.2 (Apr 28th 2015)
---------------------

* Allow SSO accross MPOS pools
  * Added a new config options
    * `$config['db']['shared']['acounts']`, defaults to `$config['db']['name']`
    * `$config['db']['shared']['workers']`, defaults to `$config['db']['name']`
    * `$config['db']['shared']['news']`, defaults to `$config['db']['name']`
  * Will access `accounts`, `pool_workers` and `news` on shared table
  * Does not allow splitting `accounts` and `pool_woker` across database hosts
  * Required `$config['cookie']['domain']` to be set
    * You need to use the top domain shared between hosts as the setting
    * e.g. `ltc.thepool.com` and `btc.thepool.com` it has to be `.thepool.com` (NOTE the leading .)
* Increased information on `Admin -> Wallet Info`
  * Added block count to Wallet Status
  * Added number of accounts to Wallet Status
  * Added Peer information
  * Added last 25 transactions
    * Can be changed via Admin System Settings -> Wallet
  * Always show all accounts
* Updated Auto Payout Threshold to be stored in `coin_address` table
  * Existing thresholds will be migrated when upgrading
  * Update to `1.0.1` for the database using the upgrade script supplied in MPOS
* Updated Bootstrap to 3.3.4
* Updated MorrisJS to 0.5.1
* Updated RaphaelJS to 2.1.2
* Updated Bootstrap Switch to 3.3.2
* Updated CLEditor to 1.4.5
* Removed unneeded JS files
* Removed unneeded CSS files
* Fixed ding for block notifications not playing on Safari
* Fixed manual payout warning to show when account balance is too low

1.0.1 (Apr 15th 2015)
---------------------

* Updated jQuery and SoundJS
* Removed unneeded JS files

1.0.0 (Jan 18th 2015)
---------------------

* First (non-beta) public release of MPOS
