# Legacy Libraries Audit (Phase 7B)

Snapshot of every bundled / vendor-like piece of code remaining in this fork, classified for future migration work. **No changes to runtime code or repository contents — this document is the deliverable.**

The earlier modernization phases (Phase 2 and Phase 3) already replaced a few bundled libraries with Composer-managed equivalents (`Memcache` shim, `Michelf/Markdown`, `KLogger`). What's documented here is what still ships in `include/lib/` and `include/smarty/`, plus a couple of vendor-like stragglers under `include/classes/`.

## Recommendation legend

- **Keep permanently** — no compelling Composer alternative; behavior depends on this code.
- **Legacy fallback** — kept as a safety net, but the active runtime path is something else.
- **Replace later** — clean Composer alternative exists; replacement is non-trivial enough to warrant its own phase.
- **Remove candidate** — code is provably dead in this fork; safe to delete in a future cleanup.

---

## include/lib/

### `Mobile_Detect.php`

| Field | Value |
| --- | --- |
| Size | 1,248 lines, ~68 KB |
| Used by | `include/autoloader.inc.php:53` (`require_once`), `:54` (`new Mobile_Detect`) |
| Runtime importance | **Critical** — runs on every request, selects mobile theme |
| Composer equivalent | [`mobiledetect/mobiledetectlib:^4.0`](https://packagist.org/packages/mobiledetect/mobiledetectlib) |
| Replacement complexity | **Medium** — v4 is namespaced (`Detection\MobileDetect`) and has API differences vs the bundled 2.x; would need either a small compat shim like the one used for KLogger, or an in-place autoloader update + the single `new Mobile_Detect` callsite touched. |
| Recommendation | **Replace later** |

### `swiftmailer/`

| Field | Value |
| --- | --- |
| Size | 171 files |
| Used by | `include/autoloader.inc.php:34-52` (autoload chain), `include/classes/mail.class.php` (Swift_SmtpTransport, Swift_SendmailTransport, etc.) |
| Runtime importance | **Critical when mail is enabled** — registration confirmations, password resets, payout notifications, IDLE-worker alerts all flow through here |
| Composer equivalent | SwiftMailer is **upstream-deprecated** (final v6 release was December 2021, package abandoned). Modern replacement is [`symfony/mailer`](https://packagist.org/packages/symfony/mailer). |
| Replacement complexity | **High** — this is a real refactor, not a drop-in. `symfony/mailer` uses a `Mailer` + DSN-based `Transport` + `Email` value-object API; `mail.class.php` would need to be rewritten end-to-end. |
| Recommendation | **Replace later** |

### `jsonRPCClient.php`

| Field | Value |
| --- | --- |
| Size | 129 lines, ~4 KB |
| Used by | `include/classes/bitcoin.class.php:241` (`class BitcoinClient extends jsonRPCClient`), `include/pages/admin/setup.inc.php` |
| Runtime importance | **Critical** — every coin-daemon RPC call (`getbalance`, `sendmany`, `getmininginfo`, `getblock`, etc.) goes through this |
| Composer equivalent | None that's drop-in. Various `*-jsonrpc-client` packages exist but all expose different APIs. A thin curl-based replacement would be ~100 lines and only marginally smaller than the bundled file. |
| Replacement complexity | **Medium** — would touch `bitcoin.class.php` (the `extends jsonRPCClient` line) plus the constructor signature in `BitcoinClient`. |
| Recommendation | **Keep** — limited upside vs. churn risk |

### `scrypt.php`

| Field | Value |
| --- | --- |
| Size | 534 lines, ~17 KB |
| Used by | `include/classes/share.class.php:293` (`Scrypt::calc(...)` for share validation against block targets) |
| Runtime importance | **Critical for scrypt-coin pools** — the share-validation cron path computes scrypt hashes on every accepted upstream share. Pools using SHA-256-d / X11 / X13 / X15 / NeoScrypt skip this path. |
| Composer equivalent | None. Pure-PHP scrypt is rare. The native [PECL `ext-scrypt`](https://github.com/DomBlack/php-scrypt) extension exists but isn't a Composer alternative — and adding a hard ext dependency would break SHA-256d-only pools that don't need it. |
| Replacement complexity | **High** — would require both an ext install path and a fallback for hosts without it. |
| Recommendation | **Keep permanently** — no Composer alternative; pure crypto code; no PHP 8 issues observed |

### `smarty_plugins/function.acl.php`

| Field | Value |
| --- | --- |
| Size | <1 KB |
| Used by | `include/smarty.inc.php:71` (`require_once`) |
| Runtime importance | **Critical** — templates use `{acl_check ...}` to gate menu items by login state |
| Composer equivalent | n/a — MPOS-custom plugin |
| Replacement complexity | n/a |
| Recommendation | **Keep permanently** — this is project-specific glue code, not a vendored library |

---

## include/smarty/ (legacy bundled Smarty 3.1.16)

| Field | Value |
| --- | --- |
| Size | 123 files (`Smarty.class.php`, `SmartyBC.class.php`, `sysplugins/` × 70+ files, `plugins/` × 50 files, `debug.tpl`) |
| Used by | `include/smarty.inc.php:18` — but **only via the `else` branch** of the `class_exists('\Smarty\Smarty')` check. Active runtime now uses `smarty/smarty:^5.0` from Composer (Phase 3A). |
| Runtime importance | **Legacy fallback** — fires only if `vendor/` is missing |
| Composer equivalent | `smarty/smarty:^5.0` (already in `composer.json`, in active use) |
| Replacement complexity | **Low** to remove — single `else` branch in `smarty.inc.php` plus the bundled tree. |
| Recommendation | **Legacy fallback** — keep for safety until Composer install is documented as mandatory; OR remove in a future phase that pairs the deletion with a clear "vendor/ is required" message in the same conditional |

**Note:** the 3 MPOS-custom modifiers (`relative_date`, `seconds_to_hhmmss`, `seconds_to_words`) live under `include/smarty/libs/plugins/modifier.*.php`. They are auto-loaded for **both** the Smarty 5 path and the legacy fallback (`smarty.inc.php:48`). If `include/smarty/` is removed, these custom modifiers must be relocated (e.g., to `include/lib/smarty_plugins/`) and `smarty.inc.php` updated to glob from there instead.

---

## include/classes/ (vendor-like stragglers)

Most files under `include/classes/` are MPOS application classes (Base, User, Block, Worker, etc.) and are not vendor-like. The exceptions worth flagging:

### `push_notification/pushover.php`

| Field | Value |
| --- | --- |
| Size | ~80 lines |
| Used by | `include/classes/pushnotification.class.php` (auto-loaded provider) |
| Runtime importance | **Optional** — only active if a user has configured Pushover for notifications |
| Composer equivalent | [`serhiy/pushover`](https://packagist.org/packages/serhiy/pushover) and similar exist, but the bundled version is small and tailored to the `IPushNotification` interface MPOS uses. |
| Replacement complexity | **Low** — single file, but would need to wrap the Composer package in the `IPushNotification` interface. |
| Recommendation | **Keep** — small, Pushover.net service is still active |

### `push_notification/notifymyandroid.php`

| Field | Value |
| --- | --- |
| Size | ~80 lines |
| Used by | `include/classes/pushnotification.class.php` (auto-loaded provider) |
| Runtime importance | **Dead** — the NotifyMyAndroid service was **shut down in February 2019**. The endpoint `https://www.notifymyandroid.com` no longer exists; calls to it would silently fail. |
| Composer equivalent | n/a — service is gone |
| Replacement complexity | n/a |
| Recommendation | **Remove candidate** — provably dead service. Removing would also need a small edit to wherever the provider is registered, but it's the closest thing to "obvious dead code" left in this tree. |

### `klogger_compat.class.php`

Not vendor — this is the Phase 3 shim that re-exposes the bundled v0.2 KLogger API on top of `katzgrau/klogger:^1.2`. **Required**, intentional, documented. Listed here for completeness only.

---

## Summary table

| Library | Files | Runtime importance | Recommendation |
| --- | --- | --- | --- |
| `lib/Mobile_Detect.php` | 1 | Critical | Replace later |
| `lib/swiftmailer/` | 171 | Critical (when mail enabled) | Replace later (real refactor) |
| `lib/jsonRPCClient.php` | 1 | Critical | Keep |
| `lib/scrypt.php` | 1 | Critical (scrypt pools) | Keep permanently |
| `lib/smarty_plugins/function.acl.php` | 1 | Critical | Keep permanently |
| `smarty/` (bundled v3.1.16) | 123 | Legacy fallback | Legacy fallback / future remove |
| `classes/push_notification/pushover.php` | 1 | Optional | Keep |
| `classes/push_notification/notifymyandroid.php` | 1 | Dead service | **Remove candidate** |

## What was already migrated (for reference)

- **`Memcache` shim** (`include/classes/memcached.class.php`) — Phase 2 — deleted; runtime uses native ext-memcached.
- **`Michelf/Markdown`** — Phase 3 — replaced by `michelf/php-markdown:^2.0` via Composer; bundled tree deleted.
- **`KLogger.php` v0.2** — Phase 3 — replaced by `katzgrau/klogger:^1.2` via Composer, fronted by `klogger_compat.class.php` for API compat. Bundled file deleted.

## Suggested order if/when migrations resume

1. **`notifymyandroid.php`** — easy single-file removal (service is dead).
2. **`Mobile_Detect.php`** — has a clear Composer target; pattern matches what KLogger did (Composer pkg + thin compat shim).
3. **`include/smarty/`** — once `composer install` is documented as mandatory, the fallback branch can be deleted; relocate the 3 custom modifiers first.
4. **`swiftmailer/`** — biggest job; standalone phase. `symfony/mailer` migration touches `mail.class.php` end-to-end.
5. **`jsonRPCClient.php`** — only worth touching if a specific Composer package becomes a clean win; otherwise keep.
6. **`scrypt.php`** — never; no replacement.
