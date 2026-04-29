# Frontend Audit (Phase 4A)

Snapshot of every JS/CSS dependency shipped in the active `bootstrap` theme. **Audit only — no changes to assets, templates, or runtime.** This document is the deliverable.

## Stack at a glance

| Layer | Current state |
| --- | --- |
| Package manager | **None** — no `package.json` / `node_modules` / lockfile. Assets are committed pre-built into `public/site_assets/`. |
| Active theme | `templates/bootstrap/` + `public/site_assets/bootstrap/` (per Phase 3A; runtime selects this via the `THEME` constant in `autoloader.inc.php`) |
| Fallback theme | `public/site_assets/global/` — only contains `js/number_format.js` and a small `images/` set |
| Module system | None — every script is a global `<script src="…">` tag in `templates/bootstrap/global/header.tpl` |
| Build step | None |

## Inventory

### Core libraries

| Library | Bundled | Released | Latest stable | Reference site |
| --- | --- | --- | --- | --- |
| **jQuery** | **2.2.4** | 2016-05-20 | 3.7.1 | `header.tpl`, master.tpl, every plugin |
| **Bootstrap (CSS+JS)** | **3.3.6** | 2015-11-25 | 5.3.x | `header.tpl` |
| **Font Awesome** | **4.6.3** | 2016-06-29 | 6.7.x | `header.tpl` |
| **Bootstrap-switch** | **3.3.2** | 2015 | (unmaintained) | `header.tpl`, account/admin toggles |

### Charting & data viz

| Library | Bundled | Released | Latest stable | Reference site |
| --- | --- | --- | --- | --- |
| **Morris.js** | **0.5.1** | 2014-04 | (archived 2016) | `header.tpl`, `dashboard/js/api.tpl`, statistics/* |
| **Raphael.js** | **2.1.2** | 2013 | 2.3.0 | `header.tpl` (Morris dependency) |
| **jquery.sparkline** | **2.1.2** | 2013 | (abandoned 2016) | `header.tpl`, statistics/blocks/*, statistics/pool/*, statistics/blockfinder/*, statistics/donors/*, statistics/uptime/* |
| **DataTables** | **1.10.12** | 2016-04 | 2.1.x | `header.tpl`, statistics tables |
| **DataTables-bootstrap** | (matched 1.10.12) | 2016 | 2.1.x | `header.tpl` |

### UI plugins

| Library | Bundled | Released | Latest stable | Reference site |
| --- | --- | --- | --- | --- |
| **metisMenu** | **2.5.2** | 2017 | 3.0.7 | `header.tpl` (sidebar nav) |
| **CLEditor** | **1.4.5** | 2014 | (vendor "Premium Software" gone) | admin news/newsletter editors |
| **jQuery UI** | (not present) | — | — | — |
| **jquery.qrcode** | **0.14.0** | 2018-12 | 0.18.0 | `account/qrcode/default.tpl` (2FA setup) |
| **zxcvbn** (Dropbox) | unversioned old build | ~2014-2015 | 4.4.2 / `@zxcvbn-ts/*` fork | password strength meter, registration |
| **soundjs** | **0.6.0** | 2015 | 1.0.2 | new-block notification ding |
| **jquery.cookie** | **1.4.1** | 2014-05 | (deprecated → js-cookie 3.x) | `header.tpl`, every page |
| **jquery.md5** | **1.2.1** | 2010 | (orphaned) | `header.tpl` |
| **jquery.equalHeight** | unversioned | ~2010-era | (effectively obsolete) | dashboard layout |
| **date.format.js** (Steven Levithan) | unversioned | ~2008-2014 | (obsolete; `Intl.DateTimeFormat` is native) | various |
| **mpos.js** | (project-local) | — | — | `header.tpl` (MPOS UI glue) |
| **pwcheck.js** | (project-local) | — | — | password fields |

### Polyfills / dead weight

| File | Purpose | Status |
| --- | --- | --- |
| `excanvas.js` | IE6-8 `<canvas>` polyfill (Google, 2007) | **Dead** — every modern browser has native canvas. Explorer Canvas hasn't been updated since IE9 dropped support 11 years ago. Referenced in `master.tpl` inside an `<!--[if IE]>...<![endif]-->` block, so it doesn't actually load on any modern browser, but still committed to the repo (~36 lines). |
| `<script src="http://html5shim.googlecode.com/svn/trunk/html5.js">` | IE<9 HTML5 shim | **Dead and broken** — `googlecode.com` shut down in 2016. The script tag in `master.tpl` will silently fail. Same `<!--[if lt IE 9]>` guard, never reaches modern browsers. |
| `bootstrap/css/ie.css` | IE-specific overrides | **Dead** — referenced inside the same IE conditional comments. |

## Per-asset risk classification

### Currently used, healthy (no upgrade urgency)

- `mpos.js`, `pwcheck.js` — project-local, no third-party version
- `jquery.qrcode 0.14.0` — close to latest (0.18.0); v1 not yet released; low risk
- `soundjs 0.6.0` → 1.0.2 is mostly a major-version cleanup; no urgent risk
- `Raphael 2.1.2` — only used as Morris's dependency; replacement order is "with Morris"

### Currently used, replacement candidate (drop-in or near-drop-in)

| Library | Recommended replacement | Risk |
| --- | --- | --- |
| `jquery.cookie 1.4.1` | `js-cookie 3.x` | Low — same author, slightly different API (`Cookies.get/set/remove` vs `$.cookie(...)`). Single-file replacement. |
| `Font Awesome 4.6.3` | FA 4 → 6 | Medium — icon class names changed (`fa-` prefix → `fas`/`far`/`fab` per family). Every template that uses `<i class="fa fa-X">` would need updating. **Big surface; defer.** |
| `metisMenu 2.5.2` | metisMenu 3.0.7 | Medium — v3 supports BS4+, drops jQuery dep. Pinned to BS3 so couples to the Bootstrap upgrade. |

### Currently used, replacement candidate (real refactor)

| Library | Replacement | Risk |
| --- | --- | --- |
| `Morris.js 0.5.1` | Chart.js 4 / ApexCharts | High — Morris was archived in 2016. API is completely different. Touches every dashboard chart. |
| `jquery.sparkline 2.1.2` | sparkline-svelte / chart.js sparkline mode / hand-rolled SVG | High — author abandoned; many MPOS pages use it. |
| `CLEditor 1.4.5` | TinyMCE / CKEditor 5 / Quill | High — admin news + newsletter editors. |
| `zxcvbn` (old Dropbox build) | `@zxcvbn-ts/core` | Medium — registration password strength check; modern fork is namespaced + tree-shakable. |
| `Bootstrap 3.3.6` | Bootstrap 5 | **Very high** — touches every template. Defer to its own multi-step phase. |

### Remove candidates (provably dead)

- **`excanvas.js`** — IE8 canvas polyfill, 36 lines. Inside `<!--[if IE]>` block. Modern browsers never load it; even IE doesn't exist anymore. Easy single-file removal + 1 line in `master.tpl`.
- **`<script src="http://html5shim.googlecode.com/...">`** — googlecode shut down 2016. Dead URL, mixed-content blocked on HTTPS. Same `master.tpl` IE block; remove the tag.
- **`bootstrap/css/ie.css`** — same dead-IE pattern.

### Project-local (not third party)

- `mpos.js` — keep
- `pwcheck.js` — keep
- `global/js/number_format.js` — keep

## Compatibility risk notes (jQuery 3 / Bootstrap 4-5 / DataTables 2)

### jQuery 2.2.4 → 3.7.x

A surface-scan for the worst breaking-change patterns in `mpos.js` + `templates/bootstrap/`:

| Deprecated/removed in jQuery 3 | Used in MPOS? |
| --- | --- |
| `.live()` | **No** |
| `.size()` | **No** |
| `$.browser` | **No** |
| `.andSelf()` | **No** |
| `.error()` event shortcut | **No** |
| `.load()` event shortcut (the one that conflicts with `.load(url)`) | **No** |
| `.bind()` / `.unbind()` (deprecated, still works) | **Yes** — shows up in inline scripts |

Every plugin we ship works with at least jQuery 1.x-2.x — the question is jQuery 3 support:

| Plugin | jQuery 3 support |
| --- | --- |
| Bootstrap 3.3.6 | Yes (BS3 v3.4.0 added jQuery 3 support; we're on 3.3.6 — would need 3.4.1 minor bump or accept that deprecation warnings show up in DevTools) |
| Bootstrap-switch 3.3.2 | Yes |
| metisMenu 2.5.2 | Yes |
| DataTables 1.10.12 | Yes |
| Morris 0.5.1 | Yes |
| Raphael 2.1.2 | Yes |
| jquery.sparkline 2.1.2 | Yes (with deprecation warnings) |
| jquery.cookie 1.4.1 | Yes |
| jquery.md5 1.2.1 | Yes |
| jquery.qrcode 0.14.0 | Yes |
| jquery.equalHeight | Mostly yes (very simple plugin; uses no removed APIs) |
| CLEditor 1.4.5 | Yes |

**Verdict:** jQuery 2.2.4 → 3.6.x or 3.7.x is **a reasonable single-step upgrade** because none of the worst-case removed APIs are in use. Bumping Bootstrap to 3.4.1 first is recommended (one-line CDN/version bump) so its own jQuery-3 fixes apply.

### Bootstrap 3 → 4 / 5

Sparser surface than expected — a grep for BS3 data-attributes found only `data-toggle` and `data-target`. BS5 renamed these to `data-bs-toggle` / `data-bs-target`. **Templates would still need updating** — but it's mechanical search-replace, not a logic rewrite. Major BS5 issues:

- BS5 dropped jQuery dependency (good) but moved everything to native JS
- Glyphicons (BS3) removed — but MPOS uses Font Awesome, so no impact there
- Grid system unchanged conceptually (`col-md-N` still works in BS5)
- Form controls renamed and restructured — some form templates would need touching
- Panels (BS3) → Cards (BS4+) — used in dashboard widgets

**Verdict:** Plausible but its own multi-step phase. **Defer.**

### DataTables 1.10.12 → 2.x

Released March 2024. Major changes:

- Drops support for jQuery <1.8 (we're fine)
- New responsive plugin built-in
- Some option names changed (e.g. `bSortable` → `orderable`); MPOS already uses the modern names per a quick scan
- Searchable export-like features moved to extensions

**Verdict:** Likely a clean upgrade. **Defer until after jQuery 3.**

### Bootstrap-switch & related

Bootstrap-switch is **unmaintained** (last release 2015). Stays jQuery 3 compatible because it uses minimal deprecated API. Replacement when needed: native HTML `<input type="checkbox" role="switch">` (BS5 has built-in switch styling) — **only** address as part of the BS5 upgrade.

## Suggested upgrade order

If/when frontend modernization moves past audit:

1. **Phase 4B — clean dead weight** (low risk, ~10 min):
   - Remove `excanvas.js`
   - Remove `bootstrap/css/ie.css`
   - Remove the `<!--[if lt IE 9]>` and `<!--[if IE]>` blocks (incl. the dead `googlecode.com/svn/trunk/html5.js`) from `master.tpl`
2. **Phase 4C — bump Bootstrap to 3.4.1** (preserves API; just gets its own jQuery-3 patches and CVE fixes; no template changes)
3. **Phase 4D — bump jQuery 2.2.4 → 3.7.1** + add `jquery-migrate 3.x` temporarily to surface any remaining deprecations
4. **Phase 4E — replace `jquery.cookie` with `js-cookie`** (single-file swap, small mpos.js call-site changes)
5. **Phase 4F — bump `metisMenu` 2.5.2 → 3.x** + `jquery.qrcode` 0.14.0 → latest
6. **Phase 4G — Font Awesome 4 → 6** (icon-class rename across templates)
7. **Phase 4H — DataTables 1.x → 2.x**
8. **Phase 4I — replace abandoned charting** (Morris.js, sparkline, Raphael) with Chart.js or ApexCharts
9. **Phase 4J — replace CLEditor** with TinyMCE/CKEditor 5/Quill
10. **Phase 4K — replace zxcvbn old build** with `@zxcvbn-ts/core`
11. **Phase 4L — Bootstrap 3 → 5** (the big one; touches every template)

Each step lands in its own commit and is verifiable with `make health` + manual page smoke before proceeding.

## What NOT to touch yet

- **Anything until `package.json` discussion happens.** The frontend currently has no package manager, no lockfile, no build step. Introducing one is its own decision (npm/yarn/pnpm? webpack/vite/none?) and shouldn't be mixed with library-version bumps.
- **Bootstrap 5 migration.** Big surface area, multi-week effort. Audit-only here.
- **Removing `bootstrap-switch`.** Coupled to BS5 migration.
- **Anything in `templates/`.** Phase 4A is asset audit only.
- **`mpos.js`, `pwcheck.js`, `number_format.js`.** Project-local; out of frontend-modernization scope.

## Summary

| Category | Count |
| --- | --- |
| Total third-party JS files in active theme | 18 |
| Total third-party CSS files in active theme | 9 |
| Provably dead / remove candidates | 3 (`excanvas.js`, `ie.css`, html5shim ref) |
| Drop-in replacement candidates | 2 (`jquery.cookie`, `jquery.qrcode`) |
| Real-refactor candidates | 6 (Morris, sparkline, CLEditor, FA, BS3, zxcvbn) |
| Healthy / project-local / keep | 7 |

Most urgent action available: **remove the 3 IE-shim leftovers** (zero risk, zero loss, removes broken cross-protocol script from the page). That would be Phase 4B.

The biggest single-step modernization win available without a full BS5 rewrite: **jQuery 2.2.4 → 3.7.x** + one-line bump of Bootstrap to 3.4.1. None of jQuery 3's removed APIs are used in MPOS code.
