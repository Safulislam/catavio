# Catavio — Implementation Plan (React Admin + Composer)

Derived from all 13 files in this folder. This document is the *how*; the numbered
phase docs remain the *what*. Where this plan changes a phase doc, the change is
called out explicitly under **Deviations from phase docs**.

- **Slug / text domain:** `catavio`
- **Namespace:** `Catavio`
- **Target:** WordPress.org Free V1, version `1.0.0`
- **Floor:** PHP 7.4+, WordPress 6.4+, WooCommerce 8.0+ (confirm during Phase 8 release testing)
- **Environment verified:** PHP 8.5.10, Composer 2.10.3, Node 22.23.2, npm 10.9.8, WordPress 7.1.2
- **Prerequisite not yet met:** WooCommerce is **not installed** in this dev site. Install it before Phase 3.

---

## 0. Two decisions that shape everything

### 0.1 React = WordPress core's React, not our own

Phase 1 forbids an unnecessary framework and Phase 5 forbids "a large frontend
framework without need". We satisfy both by consuming the React that WordPress
already loads in wp-admin:

- Build with `@wordpress/scripts` (`wp-scripts`), which marks `@wordpress/*` and
  `react`/`react-dom` as **externals** and emits an `index.asset.php` dependency
  array (`wp-element`, `wp-components`, `wp-api-fetch`, `wp-i18n`, `wp-url`).
- The shipped bundle contains only our own components. No React, no Redux, no
  router library, no CSS framework, no runtime npm dependency in the zip.
- Client-side routing is done with admin query args (`?page=…&view=…&product=…`)
  and `@wordpress/url`, so deep links, browser back, and WP menu highlighting all
  keep working. No `react-router`.
- UI is built from `@wordpress/components` so the admin looks native and inherits
  core's accessibility behaviour, which is what Phase 9 asks for.

**Consequence:** the data layer must be a REST API, not server-rendered PHP views.
This is the single largest structural change from Phase 1's file tree.

### 0.2 Composer = autoloading and dev tooling, zero runtime dependencies

- `composer.json` declares PSR-4 `Catavio\` → `includes/` only.
- **No production `require` entries.** Phase 1/8/11 forbid external services and
  favour a minimal footprint; a WP.org reviewer reads every shipped vendor file.
- Dev dependencies only: PHPUnit, WPCS, PHPCompatibilityWP, PHPUnit polyfills.
- Release build runs `composer install --no-dev --classmap-authoritative
  --optimize-autoloader`, so the shipped `vendor/` is just `autoload.php` plus
  `composer/` — a few readable files, no third-party code.
- Guard the bootstrap: if `vendor/autoload.php` is missing (someone cloned the
  repo instead of installing the zip), show an admin notice and bail; never fatal.

---

## 1. Target file structure

```text
catavio/
├── catavio.php          # header, constants, guards, bootstrap
├── uninstall.php                     # meta + option cleanup
├── readme.txt
├── LICENSE
├── composer.json / composer.lock
├── package.json / package-lock.json
├── phpcs.xml.dist / phpunit.xml.dist / .eslintrc / .distignore
├── includes/                         # PSR-4: Catavio\
│   ├── Plugin.php                    # container + wiring
│   ├── Requirements.php              # PHP/WP/WC/vendor guards + notices
│   ├── Capabilities.php              # single source of truth for the cap
│   ├── Assets.php                    # screen-scoped enqueue, asset.php deps
│   ├── Admin/
│   │   ├── Menu.php                  # menu + submenus, React mount points
│   │   └── Screen.php                # bootstrap data -> wp_add_inline_script
│   ├── Rest/
│   │   ├── Controller.php            # abstract: namespace, permission, schema
│   │   ├── SummaryController.php     # GET  /summary
│   │   ├── ProductsController.php    # GET  /products, GET /products/<id>
│   │   └── ScanController.php        # POST /scan, GET /scan, DELETE /scan
│   ├── Scanner/
│   │   ├── Scanner.php               # batch orchestration
│   │   ├── ScanState.php             # resumable job state
│   │   ├── Issue.php                 # value object
│   │   ├── Result.php                # per-product value object
│   │   └── Repository.php            # meta read/write + aggregate queries
│   ├── Checks/
│   │   ├── CheckInterface.php
│   │   ├── AbstractCheck.php
│   │   ├── Registry.php              # ordered, filterable check list
│   │   ├── TitleCheck.php  DescriptionCheck.php  ShortDescriptionCheck.php
│   │   ├── FeaturedImageCheck.php    ImageAltCheck.php
│   │   ├── PriceCheck.php   SkuCheck.php   CategoryCheck.php
│   │   ├── StockCheck.php   AttributesCheck.php
│   │   └── (variable-aware) VariationPriceCheck.php VariationSkuCheck.php
│   └── HealthScore.php               # documented, deterministic scoring
├── src/                              # JS source — SHIPPED in the zip
│   ├── index.js                      # single entry, screen switch
│   ├── api.js                        # apiFetch wrappers
│   ├── screens/Dashboard.jsx  ProductList.jsx  ProductDetail.jsx
│   ├── components/ScoreBadge.jsx  SeverityTag.jsx  ScanPanel.jsx
│   │                EmptyState.jsx  IssueList.jsx  Filters.jsx
│   ├── hooks/useScan.js  useProducts.js
│   └── style.scss
├── build/                            # wp-scripts output (index.js/.css/.asset.php)
├── languages/                        # .pot + wp i18n make-json output
└── tests/
    ├── php/{unit,integration}/
    └── js/
```

**Why `src/` ships:** WP.org requires human-readable source. Shipping the JSX
alongside the compiled bundle removes any ambiguity for the reviewer and costs a
few KB. `node_modules/`, `tests/`, and dev configs are excluded via `.distignore`.

---

## 2. Data model (no custom tables)

Phase 1 forbids a custom table until proven necessary. Post meta + one option is
enough for a catalog-sized dataset and keeps `uninstall.php` trivial.

| Key | Where | Type | Purpose |
|---|---|---|---|
| `_catavio_score` | product meta (single) | int 0–100 | sorting + health filtering |
| `_catavio_severity` | product meta (single) | `healthy\|attention\|critical` | bucket filtering |
| `_catavio_issues` | product meta (single) | array of `{check,severity}` | detail view |
| `_catavio_passed` | product meta (single) | array of check IDs | detail view "Passed" |
| `_catavio_issue_code` | product meta (**multiple rows**) | string | fast `IN()` issue filtering |
| `_catavio_scanned_at` | product meta (single) | int timestamp | "Last Checked" |
| `catavio_summary` | option (autoload **no**) | array | dashboard aggregates, top issues |
| `catavio_scan_state` | option (autoload **no**) | array | resumable scan job |
| `catavio_version` | option | string | upgrade routine hook |

Notes:
- Multiple `_catavio_issue_code` rows are what make "filter by issue type" a single
  indexed meta query instead of a PHP-side filter over the whole catalog.
- All keys are underscore-prefixed → protected meta, hidden from custom fields UI.
- `register_post_meta()` with `'show_in_rest' => false` and a restrictive
  `auth_callback`; we expose the data through our own controllers, not core REST.
- `catavio_summary` is recomputed at the end of a scan, never on page load (Phase 3).

---

## 3. REST API contract

Namespace `catavio/v1`. Every route: `permission_callback` →
`Capabilities::current_user_can_manage()`, plus `args` schema with
`sanitize_callback` and `validate_callback` on **every** parameter. Nonce is the
standard `X-WP-Nonce`, supplied automatically by core's `wp-api-fetch` middleware.

| Method | Route | Returns / does |
|---|---|---|
| `GET` | `/summary` | score average, scanned count, healthy/attention/critical, top issues, last scan time, scan status, `has_products` |
| `POST` | `/scan` | starts a job: resolves product ID set, writes `catavio_scan_state`, returns `{total, batch_size, processed}` |
| `POST` | `/scan/batch` | processes one batch, returns `{processed, total, done}` |
| `GET` | `/scan` | current progress (for resume after reload) |
| `DELETE` | `/scan` | aborts and clears job state |
| `GET` | `/products` | paginated list. Params: `page`, `per_page` (≤100), `search`, `health` (enum), `issue` (check ID enum), `type` (WC type enum), `orderby`, `order`. Sends `X-WP-Total` / `X-WP-TotalPages` |
| `GET` | `/products/<id>` | one product: name, type, score, issues by severity, passed checks, `edit_url`, `last_checked` |

Response shaping rules:
- Never return raw product objects; map to an explicit array per route.
- `edit_url` is built server-side with `get_edit_post_link()` so the React app
  never constructs admin URLs itself.
- Issue **messages** are translated server-side (Phase 9 forbids passing runtime
  variables as translation strings); the client renders text it is given.

---

## 4. Scanner and checks design

### 4.1 Check contract

```php
interface CheckInterface {
    public function get_id(): string;                        // 'missing_sku'
    public function get_label(): string;                     // translated
    public function get_severity(): string;                  // critical|warning|info
    public function applies_to( \WC_Product $product ): bool; // product-type awareness
    public function passes( \WC_Product $product ): bool;
    public function get_message(): string;                   // translated, static
}
```

`Registry` holds checks in a fixed order and exposes them through a filter
(`catavio_checks`) so the Pro boundary stays clean without any Free
code referencing Pro. Determinism is a hard requirement: no randomness, no time
dependence, no network.

### 4.2 Applicability matrix (Phase 3's "do not apply every rule to every type")

| Check | Simple | Variable | Grouped | External | Virtual | Downloadable |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| Title | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Description | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Short description | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Featured image | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Image ALT text | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Category | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Price | ✓ | via variations | — (derived) | ✓ + product URL | ✓ | ✓ |
| SKU | ✓ | ✓ + per variation | ✓ | ✓ | ✓ | ✓ |
| Stock config | ✓ | ✓ | — | — | ✓ (if managed) | ✓ (if managed) |
| Attributes | info only | **required** (must have variation attributes) | — | info only | info only | info only |
| Downloadable files | — | — | — | — | — | ✓ (files present, limits sane) |
| External product URL | — | — | — | ✓ | — | — |

Rules encoded from Phase 3: virtual products are never judged on shipping;
grouped products derive price from children so price is not scored; attributes
are only a hard requirement for variable products.

### 4.3 Batching

- Batch size 50, filterable, auto-reduced if a batch exceeds a wall-clock budget.
- Product IDs are resolved once at job start (`'fields' => 'ids'`, `posts_per_page`
  capped, `no_found_rows`), stored as a chunked list in `catavio_scan_state`.
- Each batch: `wc_get_products()` for the slice → run checks → write meta →
  update state → return progress. A product deleted mid-scan is skipped, counted,
  and never fatals (Phase 8 failure test).
- `delete_post_meta` for `_catavio_issue_code` before re-inserting rows, so re-scans
  do not accumulate stale rows.
- Interruption safety: state is persisted per batch, so a reload resumes from
  `GET /scan`. A job older than 1 hour is considered stale and offered for reset.
- Absolutely no scan on admin page load — the dashboard only reads `catavio_summary`.

### 4.4 Scoring (Phase 4)

```
score = 100
  - 20 per critical issue
  - 10 per warning
  -  5 per info
floor at 0
```
Only checks where `applies_to()` is true participate. The constants live in
`HealthScore` with a docblock stating the rules verbatim, because Phase 4 requires
the scoring be documented in code and reproducible from visible checks.

Buckets are decided by **severity, not by a score threshold** (this supersedes an
earlier draft of this plan, which proposed `attention` below a score of 90):
`critical` when there is at least one critical issue, `attention` when there is
any issue but none critical, `healthy` when nothing was found. A threshold would
make "why is this product in this group" unanswerable without knowing a magic
number, and would shift meaning every time a penalty was tuned. Severity buckets
stay explainable from the issue list alone, which is Phase 4's exit criterion.

---

## 5. Admin screens (React)

Three submenus under one top-level menu, each mounting the same bundle with a
different `screen` value in the bootstrap object:

1. **Dashboard** (`page=catavio`) — overall score, scanned/healthy/
   attention/critical counts, top issues, last scan time, `Run Scan` button with a
   progress bar and cancel, plus the two Phase 5 empty states.
2. **Product Health** (`page=catavio-products`) — paginated table
   with search, health filter, issue filter, type filter; columns Product / Type /
   Health / Issues / Last Checked / Actions (View detail, Edit product).
3. **Detail** (`…-products&product=123`) — score, issues grouped Critical /
   Warning / Info, passed checks, last checked, and a single primary
   **Edit Product** action pointing at the normal WooCommerce editor. We build no
   replacement editor (Phase 7).

Bootstrap data is passed with `wp_add_inline_script( $handle, 'var catavioAdmin = …',
'before' )` using `wp_json_encode` — never `wp_localize_script` for structured
data, and never inline HTML.

Accessibility (Phase 9), enforced in components not as an afterthought:
- Every status carries a text label; colour is decoration only. `ScoreBadge`
  renders "61/100 — Needs attention", `SeverityTag` renders the word.
- Scan progress announced through `@wordpress/a11y` `speak()` and an
  `aria-live="polite"` region; `<progress>` with accessible name.
- Filters are real labelled form controls; the table uses `<th scope="col">`.
- Focus is moved to the detail heading on client-side navigation.
- Keyboard-only pass over each screen is a Phase 9 exit gate.

---

## 6. Security model (Phases 2, 8, 11)

- **Capability:** `manage_woocommerce`, falling back to `edit_products` when
  WooCommerce has not registered its caps. Resolved in one place,
  `Capabilities::current_user_can_manage()`, used by the menu, by `Assets`, and by
  every `permission_callback`. Never `is_admin()` as an authorization check.
- **Nonce:** `X-WP-Nonce` on every REST call (core's apiFetch middleware). Write
  routes (`POST`/`DELETE /scan`) additionally re-verify capability per request.
- **Input:** every REST arg has `sanitize_callback` + `validate_callback`; enums
  for `health`, `issue`, `type`, `orderby`, `order`; `absint` on IDs and paging;
  `per_page` hard-capped at 100.
- **Queries:** `WP_Query`/`wc_get_products` only. If any raw SQL becomes necessary
  for aggregates it uses `$wpdb->prepare` and is reviewed explicitly.
- **Output:** all PHP output escaped at the point of echo; React escapes by
  default and we use no `dangerouslySetInnerHTML` anywhere.
- **No** external HTTP, telemetry, license check, remote code, or admin hijacking.

---

## 7. Composer and npm configuration

**composer.json**
```json
{
  "name": "thdevs/catavio",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": { "php": ">=7.4" },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "yoast/phpunit-polyfills": "^2.0",
    "squizlabs/php_codesniffer": "^3.9",
    "wp-coding-standards/wpcs": "^3.1",
    "phpcompatibility/phpcompatibility-wp": "^2.1"
  },
  "autoload": { "psr-4": { "Catavio\\": "includes/" } },
  "config": {
    "optimize-autoloader": true,
    "allow-plugins": { "dealerdirect/phpcodesniffer-composer-installer": true }
  },
  "scripts": {
    "lint": "phpcs",
    "fix": "phpcbf",
    "test": "phpunit"
  }
}
```

**package.json scripts**
```
build       wp-scripts build
start       wp-scripts start
lint:js     wp-scripts lint-js
lint:css    wp-scripts lint-style
test:js     wp-scripts test-unit-js
makepot     wp i18n make-pot . languages/catavio.pot
makejson    wp i18n make-json languages --no-purge
```

Only `@wordpress/scripts` (and `@wordpress/components`/`element`/`api-fetch`/
`i18n`/`url`/`a11y` as externals-only deps) are installed. Nothing from npm is
shipped.

---

## 8. Phase-by-phase execution order

Each step ends with a runnable, testable state. Do not start the next until the
previous one's exit criteria pass.

| # | Phase doc | Work | Done when |
|---|---|---|---|
| 1 | P1 | Write `docs/ARCHITECTURE.md` reflecting §1–§4 above; create dir skeleton, `composer.json`, `package.json`, `phpcs.xml.dist`, `.distignore` | `composer install` and `npm install` succeed; `npm run build` emits `build/index.asset.php` |
| 2 | P2 | Plugin header, constants, `Requirements`, `Plugin`, `Capabilities`, `Assets`, `Menu`, activation/deactivation, `load_plugin_textdomain`, HPOS declaration, a React "hello" mount | Activates with and without WooCommerce; notice shown when inactive; no fatals; assets load only on our 3 screens |
| 3 | P3 | `CheckInterface`, `AbstractCheck`, `Registry`, all checks per §4.2, `Issue`/`Result`, `Repository`, `Scanner`, `ScanState`, `ScanController` | Unit tests for all 12 Phase-3 scenarios pass; a manual `POST /scan` scans a seeded catalog in batches |
| 4 | P4 | `HealthScore` with documented constants; wire into `Result`; recompute `catavio_summary` at job end | Phase-4 test list passes, including floor-at-zero and type-specific cases |
| 5 | P5 | `SummaryController`; `Dashboard.jsx`, `ScanPanel.jsx`, `EmptyState.jsx`, `useScan` (start → batch loop → progress → resume → cancel) | Dashboard reads only `catavio_summary`; both empty states render; progress is announced to screen readers |
| 6 | P6 | `ProductsController` list route with meta-query filters; `ProductList.jsx`, `Filters.jsx`, pagination | 1,000-product catalog pages without a slow query; every filter param validated; links open the WC editor |
| 7 | P7 | `/products/<id>`; `ProductDetail.jsx`, `IssueList.jsx`, `ScoreBadge`, `SeverityTag` | Score is fully explained by the listed issues; `Edit Product` opens the standard editor; keyboard pass clean |
| 8 | P8 | PHPUnit unit + integration suites, PHPCS to zero, `php -l` sweep, ESLint/Jest, security sweep of every route/param/output, performance sweep, all seven failure tests | Phase-8 exit criteria: no known blocker |
| 9 | P9 | `.pot` + `make-json`, `wp_set_script_translations`, translator comments, full a11y pass | Every user-facing string is translation-ready; core workflows keyboard-accessible |
| 10 | P10 | `readme.txt`, `LICENSE`, `uninstall.php`, screenshots, `.distignore`, release build script | Zip contains only production files, builds clean, no secrets/paths |
| 11 | P11 | Walk the Phase-11 checklist on a clean WP + WC install, all 12 release steps | Checklist complete; submit (approval is WP.org's call, never claimed here) |

---

## 9. Testing plan

**PHP unit** (no WP bootstrap, mocked `WC_Product`): every check's `applies_to`
and `passes` across all six product types; `HealthScore` arithmetic including
floor; `Registry` ordering.

**PHP integration** (WP test suite + WooCommerce): scanner over a seeded catalog;
batch resume; product deleted mid-scan; invalid product ID; REST routes for
authorized, unauthorized, and logged-out users; nonce rejection; `uninstall.php`
leaves no `_catavio_*` meta or `catavio_*` options behind.

**JS** (Jest + @testing-library): `ScoreBadge`/`SeverityTag` render text not just
colour; `useScan` batch loop, cancel, and resume-after-reload; filter state ↔ URL
query args round-trip; empty states.

**Manual matrix:** WooCommerce inactive · zero products · 5,000 products ·
scan interrupted by reload · Editor/Author/Subscriber access attempts ·
keyboard-only traversal · browser console clean.

---

## 10. Deviations from the phase docs

1. **`admin/views/*.php` are replaced** by `includes/Rest/*` controllers plus
   `src/screens/*.jsx`. Phase 1's tree assumed server-rendered views. The security
   model, business-logic-out-of-presentation rule, and data flow are unchanged —
   only the transport is.
2. **Namespacing style:** `includes/Plugin.php` (PSR-4, StudlyCase) instead of
   `includes/class-plugin.php`. Composer PSR-4 autoloading requires it. WPCS is
   configured to exempt `includes/` from the file-naming sniff and nothing else.
3. **A REST API exists** where the docs only mention "REST route if any". Phase 8
   and 11 already cover auditing REST routes, so this is in scope, not new scope.
4. **Node/npm become build-time requirements.** `build/` is committed and shipped
   so no end user ever needs Node. `src/` ships for reviewer readability.
5. Everything else — no custom tables, no external requests, no telemetry, no AI,
   no Pro locks, manual scan only, deterministic checks — is unchanged.

## 11. Open items to confirm before Phase 3

- Install WooCommerce in this dev site (currently absent).
- Confirm the minimum WooCommerce version to support, then set `WC requires at
  least` in the plugin header.
- Confirm plugin author/contributor slug for `readme.txt`.
