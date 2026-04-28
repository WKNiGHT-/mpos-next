# MPOS Next v2.0.0 — PHP 8 + Docker Modernization

First public release of the modernized MPOS fork. The codebase has been brought up to PHP 8.3, wired into a self-contained Docker dev environment, and re-engineered to use Composer-managed dependencies — all without touching the templates, schema, or core business logic of the upstream project.

The original MPOS team and contributors retain full credit for the application; this release is purely the modernization layer on top.

## Highlights

### Docker-first setup

A four-service `docker-compose.yml` (PHP 8.3 + Apache, MySQL 8, memcached, phpMyAdmin) lets a new developer go from `git clone` to a working local instance in three commands:

```bash
cp .env.example .env
make up
make bootstrap-config
```

A `Makefile` wraps everything else: `make health`, `make logs`, `make shell`, `make mysql`, `make php-lint`, `make cron-<name>`, `make nuke`. See `README-DOCKER.md` for the full Docker reference.

### PHP 8.3 compatibility

A multi-phase sweep covered:

- **Parse-level fatals** (Phase 1B): curly-brace string offsets in `Markdown.php` and `SwiftMailer`, `create_function()` removal, PHP-4-style ctors in the bundled XML/JSON-RPC, duplicate `static` declarations, and the `Exception::$line` typed property tightening in bundled Smarty 3.
- **Runtime fatals** (Phase 1C): `count(null)` guard, Smarty engine collisions during the early V3↔V5 transition.
- **Deprecations** (Phases 1D / 1E / 1F / 1G): array-offset-on-null, `session_regenerate_id` after `session_destroy`, optional-before-required parameters across `share`/`statistics`/`payout`/`roundstats`, `#[\ReturnTypeWillChange]` on `mysqlims` overrides, `#[\AllowDynamicProperties]` on classes that write undeclared properties, `false → []` for the `sasl` config, and a `HTTP_USER_AGENT` default to silence the bundled Mobile_Detect's `preg_match(null)` deluge.

Result: every smoked-tested page and cronjob runs with **zero PHP warnings, deprecations, or fatals** under PHP 8.3.

### Composer dependency management

Bundled libraries replaced with Composer-managed packages, one at a time:

| Library | Replaced by |
| --- | --- |
| `Michelf/Markdown` | `michelf/php-markdown:^2.0` (same author, same namespace — drop-in) |
| `KLogger.php` | `katzgrau/klogger:^1.2` + `psr/log` (PSR-3, fronted by a v0.2-API shim so all 195 callsites work unchanged) |
| `Memcache` shim | Removed entirely — native `ext-memcached` everywhere |

`Mobile_Detect.php` and `swiftmailer/` remain bundled for now (replacement is on the roadmap; both have non-trivial migrations).

### Smarty 5 integration

`smarty/smarty:^5.0` is now the active rendering engine. Key design choices:

- The namespaced `\Smarty\Smarty` class is what makes coexistence safe — the legacy bundled Smarty 3.1.16 stays under `include/smarty/` as a fallback path that fires only if Composer's `vendor/` is missing. One Smarty per request, no class collisions.
- **Templates were not modified.** Custom MPOS plugins (`relative_date`, `seconds_to_hhmmss`, `seconds_to_words`) are auto-discovered from the bundled plugins directory and registered for V5; PHP built-in modifiers (`file_exists`, `count`, `strlen`, `round`, `explode`) are explicitly registered.
- The registration form's missing-template-variable warnings are silenced controller-side via `$_POST` defaults (Phase 3B).

### Memcache → Memcached migration

The Windows-only `class Memcached extends Memcache` shim that masked native `ext-memcached` on `*nix` is gone. Runtime now uses native PHP `Memcached` on every platform. SASL auth still gated behind `$config['memcache']['sasl']`.

### DB connection hardening

`include/database.inc.php` rewritten to:

- Catch `mysqlims` constructor exceptions and emit a clean error message that names host:port without exposing credentials. No more PHP backtrace leaking on connection failure.
- Force `SET NAMES 'utf8mb4'` on the active connection (defensive against hosts where the server-side default differs).
- Null-safe read-only check (no fatal on chained method calls if the SHOW returns false).
- Re-ordered so the connect-error path runs before any query.

### Clean cron + web runtime

`make php-lint` reports clean across the entire project. Smoke tests run on:

- Web: `/`, `/?page=login`, `/?page=register`, `/?page=blocks`, `/?page=workers` — all HTTP 200.
- Cron: `statistics`, `tickerupdate`, `notifications`, `token_cleanup`, `archive_cleanup` — all 0 deprecations / 0 warnings / 0 fatals.

### Original MPOS credit preserved

`README.md`, `CHANGELOG.md`, and the upstream contributing/team sections remain intact. `RELEASE_NOTES.md` and the new MPOS Next sections are layered above, never replacing.

## Upgrade path

If you're coming from upstream MPOS or an older fork: there is no schema change, no required config rename, and no template change. Configuration keys map 1:1. Drop the new tree in, run `composer install`, and you're done. See the **Upgrade notes** section in `README.md` for the full surface-by-surface table.

## What's next (roadmap)

- Mobile_Detect Composer migration (4.x has API breaks — needs a migration shim similar to KLogger's)
- SwiftMailer → Symfony Mailer (real refactor; SwiftMailer is upstream-deprecated)
- Frontend modernization (jQuery 2 → 3, plugin audit)
- Installation wizard polish
- Optional Bitcoin Core / node-first deployment profiles

## Acknowledgements

Original MPOS — Sebastian Grewe ([TheSerapher](https://github.com/TheSerapher)) and the listed contributors at https://github.com/MPOS/php-mpos. MPOS Next is built entirely on top of their work.
