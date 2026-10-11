# Catavio — Architecture

Phase 1 deliverable. This is the developer reference for how the plugin is put
together. The product requirements live in `catavio-development-docs/`;
this document records the technical decisions taken to satisfy them.

- **Namespace:** `Catavio`
- **Text domain / slug:** `catavio`
- **Prefixes for non-namespaced symbols:** `CATAVIO_` (constants), `catavio_` (options,
  meta, transients), `catavio_` (hooks)
- **Floor:** PHP 7.4, WordPress 6.4, WooCommerce 8.0 (confirmed in Phase 8)

---

## 1. Data flow

```text
WooCommerce products
  └─> Scanner            batched, manual trigger only
        └─> Checks       product-type aware, deterministic
              └─> Issues
                    └─> HealthScore
                          └─> Repository   (post meta + summary option)
                                └─> REST controllers
                                      └─> React admin
                                            └─> standard WooCommerce product editor
```

Two properties of this flow are load-bearing and must not be broken:

1. **Scanning is never triggered by rendering.** Admin screens read stored
   results only. The scan runs solely from an explicit user action.
2. **The plugin never edits products.** The final hop is always a link into the
   normal WooCommerce editor. There is no replacement editor.

---

## 2. Layers and responsibilities

| Layer | Namespace | Knows about |
|---|---|---|
| Bootstrap | `Catavio\` | WordPress only. Guards, wiring, lifecycle. |
| Admin shell | `Catavio\Admin\` | Menus, screen registration, asset enqueue, bootstrap data. No business logic. |
| Transport | `Catavio\Rest\` | Request validation, capability checks, response shaping. No business logic. |
| Domain | `Catavio\Checks\`, `\Scanner\`, `HealthScore` | WooCommerce products and quality rules. No HTTP, no output, no escaping. |
| Persistence | `Catavio\Scanner\Repository` | Post meta and options. The only place storage keys appear. |
| Presentation | `src/` (React) | Rendering only. Receives text it does not construct. |

The rule that keeps this honest: **a domain class may not call `esc_*`,
`wp_send_json_*`, or touch `$_GET`/`$_POST`; a transport or presentation class
may not decide whether a product passes a check.**

---

## 3. File structure

```text
catavio/
├── catavio.php     # header, constants, guards, bootstrap   (Phase 2)
├── uninstall.php                                                          (Phase 10)
├── readme.txt · LICENSE                                                   (Phase 10)
├── composer.json · package.json · phpcs.xml.dist · .distignore            (Phase 1 ✓)
├── includes/                    # PSR-4 root: Catavio\
│   ├── Plugin.php · Requirements.php · Capabilities.php                   (Phase 2 ✓)
│   ├── Lifecycle.php · Assets.php                                         (Phase 2 ✓)
│   ├── Admin/     Menu.php · Screen.php                                   (Phase 2 ✓)
│   ├── Rest/      Controller.php · SummaryController.php
│   │              ProductsController.php · ScanController.php           (Phases 5–7)
│   ├── Scanner/   Scanner.php · ScanState.php · Issue.php
│   │              Result.php · Repository.php                             (Phase 3)
│   ├── Checks/    CheckInterface.php · AbstractCheck.php · Registry.php
│   │              + one class per check                                   (Phase 3)
│   └── HealthScore.php                                                    (Phase 4)
├── src/                         # JSX source — ships in the zip
│   ├── index.js · style.scss · api.js
│   ├── screens/    Dashboard · ProductList · ProductDetail
│   ├── components/ ScoreBadge · SeverityTag · ScanPanel · IssueList
│   │               Filters · EmptyState
│   └── hooks/      useScan · useProducts
├── build/                       # wp-scripts output, committed and shipped
├── assets/images/ · languages/ · docs/ · tests/ · bin/
└── vendor/                      # Composer autoloader only, no third-party code
```

### Autoloading

`includes/` is a PSR-4 root, so its files are `StudlyCase.php` matching the class
name rather than WordPress's `class-*.php` convention. This is the **only**
deviation from WPCS file naming, and `phpcs.xml.dist` waives
`WordPress.Files.FileName` for `includes/` and `tests/` and nowhere else.

`composer.json` has **no production `require` entries** beyond the PHP version.
The release build runs:

```sh
composer install --no-dev --classmap-authoritative --optimize-autoloader
```

so the shipped `vendor/` is the Composer autoloader and nothing else — a handful
of readable files for a WordPress.org reviewer, no vendored third-party tree.

`config.platform.php` is pinned to `7.4.33` so dependency resolution always
targets the supported floor rather than the developer's local PHP.

---

## 4. Frontend architecture

The admin UI is React, but the plugin ships **no framework**. `@wordpress/scripts`
treats `react`, `react-dom`, and every `@wordpress/*` package as webpack
externals, mapping them to the globals WordPress already loads in wp-admin. The
generated `build/index.asset.php` declares them as script dependencies.

Verified in Phase 1:

```php
// build/index.asset.php
return array(
    'dependencies' => array( 'react-jsx-runtime', 'wp-element', 'wp-i18n' ),
    'version'      => '…',
);
```

with a 1.1 KB bundle that references `wp.element` and `wp.i18n` and contains no
copy of React. The dependency array grows as screens are built
(`wp-components`, `wp-api-fetch`, `wp-url`, `wp-a11y`); `Assets.php` must read it
from the file rather than hard-coding a list.

Build output paths, for `Assets.php`:

| File | Handle role |
|---|---|
| `build/index.js` | script |
| `build/index.asset.php` | `dependencies` + `version` |
| `build/style-index.css` | stylesheet (note the `style-` prefix) |
| `build/style-index-rtl.css` | auto-selected by `wp_style_add_data( …, 'rtl', 'replace' )` |

Other frontend decisions:

- **One admin page is registered, not two or three.** `page=catavio`
  renders the dashboard, and `&tab=products` renders Product Health. The product
  detail report is a *view* within that tab, addressed by a `product` query
  argument. WordPress repeats a top level menu item as its own first submenu the
  moment any submenu exists, which put two sidebar routes to one page; no
  submenus are registered, and the tab bar names the views instead.
- **The `<h1>` and the tab bar are rendered server side** by `Admin\Screen`,
  outside the React tree, so the page is titled, navigable and announced
  correctly before the bundle runs — and still is if the bundle fails to run. A
  `<noscript>` notice covers that case. The heading names the section and the
  tabs name the view, so the sidebar entry and the page never disagree.
- **The hook suffix is captured, never guessed.** `Menu` stores the return value
  of `add_menu_page()` and `Assets` reads it back, because a suffix derived from
  the menu title breaks the moment that title changes.
- **No router library.** Navigation uses admin query args
  (`?page=…&tab=…&product=…`) via `@wordpress/url`, so deep links and browser
  back keep working. The page slug and the tab argument reach the browser in the
  bootstrap data rather than being written out in JavaScript, so renaming either
  cannot strand the client on a URL the plugin no longer answers.
- **UI from `@wordpress/components`**, so the admin looks native and inherits
  core's keyboard and screen-reader behaviour instead of restating it.
- **`src/` ships in the zip.** WordPress.org expects human-readable source for
  compiled assets; the JSX is a few KB and removes any ambiguity.
- **No `dangerouslySetInnerHTML` anywhere.**
- **`typescript` is pinned to `^5.9.0` in devDependencies.** The plugin contains
  no TypeScript. The pin exists because `@wordpress/scripts` 30 ships
  `@typescript-eslint` 6.x, whose peer range (`typescript >= 4.2`) lets npm hoist
  TypeScript 7, which it cannot parse — `wp-scripts lint-js` then dies with
  `Cannot read properties of undefined (reading 'Intrinsic')`. Dev-only; it never
  reaches the zip. Revisit when wp-scripts upgrades `@typescript-eslint`.
- **Strings are translated where they are decided.** Issue messages are
  translated in PHP and sent as text; the client never assembles a sentence from
  a runtime variable. UI chrome uses `@wordpress/i18n` with
  `wp_set_script_translations()`.

---

## 5. Storage model

No custom database table. Phase 1 forbids one until proven necessary, and post
meta plus one option covers everything the screens need.

| Key | Location | Cardinality | Purpose |
|---|---|---|---|
| `_catavio_score` | product meta | single, int 0–100 | sorting, health filter |
| `_catavio_severity` | product meta | single, enum | `healthy` / `attention` / `critical` bucket |
| `_catavio_issues` | product meta | single, array | detail view |
| `_catavio_passed` | product meta | single, array | detail view "Passed checks" |
| `_catavio_issue_code` | product meta | **one row per issue** | indexed `IN()` filtering by issue type |
| `_catavio_scanned_at` | product meta | single, int | "Last Checked" |
| `catavio_summary` | option, no autoload | array | dashboard aggregates, top issues |
| `catavio_scan_state` | option, no autoload | array | resumable scan job |
| `catavio_version` | option | string | upgrade routines |

Notes:

- `_catavio_issue_code` is deliberately multi-row. It is what makes "filter the list
  by issue type" a single indexed meta query instead of loading the catalog into
  PHP. Re-scans delete the rows before re-inserting them so nothing accumulates.
- All meta keys are underscore-prefixed, so they are protected and hidden from
  the custom fields UI. They are registered with `show_in_rest => false` and a
  restrictive `auth_callback`; the data is exposed only through our controllers.
- `catavio_summary` is recomputed at the end of a scan job — never on page load.
- Everything above is removed by `uninstall.php`.

---

## 6. REST API

Namespace `catavio/v1`. Chosen over `admin-ajax` because it gives
schema-level argument validation, real HTTP semantics for pagination, and
automatic nonce handling through core's `wp-api-fetch` middleware.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/summary` | dashboard aggregates and scan status |
| `POST` | `/scan` | start a job; resolves the product ID set |
| `POST` | `/scan/batch` | process one batch, return progress |
| `GET` | `/scan` | current progress (resume after reload) |
| `DELETE` | `/scan` | abort and clear job state |
| `GET` | `/products` | paginated list with filters; sends `X-WP-Total` |
| `GET` | `/products/<id>` | one product's full health report |

Controllers never return WooCommerce objects. Each route maps to an explicit
response array, and `edit_url` is built server-side with `get_edit_post_link()`
so the client never constructs admin URLs.

---

## 7. Security model

Every privileged operation carries all four of:

1. **Capability check** — `manage_woocommerce`, falling back to `edit_products`
   and finally `manage_options` when WooCommerce has not registered its caps.
   Resolution asks the role objects which capability actually exists rather than
   assuming, and is memoized. It lives in exactly one place,
   `Capabilities::get()`, used by the menu, the asset enqueue, `Screen::render()`
   and every `permission_callback`. `is_admin()` is never used as an
   authorization check. Verified without WooCommerce: resolves to
   `manage_options`; administrator allowed, editor/author/contributor/subscriber
   and logged-out users denied, and `Screen::render()` `wp_die()`s with 403 for a
   subscriber.
2. **Nonce verification** — `X-WP-Nonce` on every REST request, supplied
   automatically by core's `wp-api-fetch` middleware.
3. **Input validation and sanitization** — every REST argument declares both
   `sanitize_callback` and `validate_callback`. `health`, `issue`, `type`,
   `orderby` and `order` are enums; IDs and paging use `absint`; `per_page` is
   capped at 100.
4. **Output escaping** — escaped at the point of echo in PHP. React escapes by
   default and no component uses `dangerouslySetInnerHTML`.

Queries go through `WP_Query` / `wc_get_products`. If a raw aggregate query ever
becomes necessary it uses `$wpdb->prepare` and is called out in review.

Out of scope by policy, not by omission: external HTTP requests, telemetry,
license checks, remote code, admin notices outside our own screens.

---

## 8. Dependency and failure model

The plugin must never fatal. `Requirements` (Phase 2) checks, in order:

1. `vendor/autoload.php` exists — otherwise an admin notice and bail. This
   catches the case where the repository was cloned instead of the zip installed.
2. PHP >= 7.4, WordPress >= 6.4.
3. WooCommerce is active — otherwise the Phase 2 admin notice, and **no
   WooCommerce-dependent code is loaded at all**. Nothing in `Scanner\` or
   `Checks\` is constructed before `woocommerce_init`.

When WooCommerce is absent or too old, the plugin activates normally, registers
**no admin menu at all**, and shows a single explanatory notice. Nothing in
`Scanner\` or `Checks\` is constructed.

### The `Requires Plugins` header, and why `Requirements` survives it

The plugin ships `Requires Plugins: woocommerce`. WordPress 6.5+ enforces it by
disabling the Activate link until WooCommerce is installed and active, and by
stating the dependency in the installer before anything is downloaded. That is
the earliest and clearest place to say it, so it is said there.

This was previously omitted on the grounds that it made the "activate cleanly,
then explain via a notice" path unreachable. That reasoning was incomplete: the
header answers one question of four, and `Requirements` still owns the other
three.

| Question | Answered by |
|---|---|
| Is WooCommerce active? | The header, on WP 6.5+; `Requirements`, on WP 6.4 |
| Is WooCommerce 8.0 or newer? | `Requirements` only — the header is version-blind |
| Is PHP 7.4 or newer? | `Requirements` only |
| Is WordPress 6.4 or newer? | `Requirements` only |

So the header narrows when the notice path is reached; it does not retire it. On
WordPress 6.4 — the floor this plugin supports — the header is ignored outright
and the notice is the only thing standing between a missing dependency and a
fatal error. The `Requirements` tests therefore still cover every branch, and
the class is unchanged.

Behaviour with the header present, as WordPress reports it when WooCommerce is
absent on 6.5+: *"Failed to activate plugin. Catavio requires 1 plugin to be
installed and activated: woocommerce."* The plugin never reaches its own code in
that case, which is the intended outcome.

HPOS: the plugin touches no order data, so it declares
`custom_order_tables` compatibility via `FeaturesUtil::declare_compatibility()`
on `before_woocommerce_init`. `cart_checkout_blocks` is declared for the same
reason.

---

## 9. Performance rules

- Scanning is batched (50 products, filterable, auto-reduced under a wall-clock
  budget) and driven from the client, one HTTP request per batch.
- Product IDs are resolved once per job with `'fields' => 'ids'` and
  `no_found_rows`, then chunked into `catavio_scan_state`.
- A product deleted mid-scan is skipped and counted, never fatal.
- Job state is persisted per batch, so a reload resumes rather than restarts.
- Admin screens are paginated; the catalog is never loaded whole.
- Assets enqueue only on this plugin's screens, never globally.
- No autoloaded options carrying scan data.

---

## 10. Check rules and product-type awareness

Thirteen checks, run in registry order. A check that does not apply to a product
is excluded entirely: neither failed nor passed, and it will not affect the
Phase 4 score.

| Check id | Severity | Applies to |
|---|---|---|
| `missing_title` | critical | all |
| `missing_description` | critical | all |
| `missing_short_description` | warning | all |
| `missing_price` | critical | all except grouped |
| `missing_featured_image` | warning | all |
| `missing_image_alt_text` | info | products that have a featured image |
| `missing_category` | warning | all |
| `missing_sku` | warning | all |
| `incomplete_stock_configuration` | warning | all except external and grouped |
| `no_variations` | critical | variable only |
| `missing_variation_attributes` | critical | variable only |
| `missing_external_url` | critical | external only |
| `missing_downloadable_files` | critical | downloadable only |

Decisions worth keeping, each verified against a real WooCommerce catalog:

- **Grouped products are not judged on price or stock.** WooCommerce derives both
  from the children, so demanding them on the parent reports a defect that does
  not exist.
- **Attributes are only required of variable products.** The specification calls
  this out directly. A simple product with no attributes is not a defect, so no
  attribute check applies to it at all.
- **Variable products are checked without loading their variations.** WooCommerce
  exposes the lowest variation price through `get_price()` from its own cache, so
  "no variation is priced" costs no extra queries.
- **Virtual products are never judged on shipping**, and are only asked for
  downloadable files when they are actually downloadable.
- **Alt text is only checked when there is an image.** Reporting both a missing
  image and its missing alt text would penalise one problem twice.
- **The default category counts as uncategorised.** WooCommerce assigns
  "Uncategorized" to every product saved without a category, so a check for an
  empty list alone would never fire. This was only visible against real
  WooCommerce; fakes could not have shown it.
- **A zero stock quantity is not a missing one.** Zero is a legitimate quantity.

---

### Scoring

Defined in full in `HealthScore`, and deliberately simple enough that a store
owner can reproduce any number by hand:

```
100
 -20  per critical issue
 -10  per warning
 -5   per informational finding
  0   floor
```

Only checks that apply to the product take part, so a grouped product is never
penalised for the price it does not have.

Buckets are decided by **severity, not by a score threshold**: `critical` when
there is at least one critical issue, `attention` when there is any issue but
none critical, `healthy` when nothing was found. A threshold such as "below 90"
would make "why is this product in this group" unanswerable without knowing a
magic number, and would silently change meaning whenever a penalty is tuned.
Severity buckets stay explainable from the issue list alone, which is what the
product detail screen promises. Two results can therefore share a score of 80
and sit in different buckets, one having a single critical issue and the other
two warnings; that is intended.

`HealthScore::explain()` returns the deductions as a list so the detail screen
can show the arithmetic rather than assert the total.

### The summary is accumulated, never queried

Catalog totals are accumulated in the scan job as products are processed and
written to `catavio_summary` once, when a job runs to completion. The dashboard
therefore renders from a single option read, and opening an admin screen never
touches the catalog. A cancelled or abandoned job writes nothing, so the figures
on screen always describe a scan that actually finished.

---

### The scan loop lives in the browser

The client starts a job, then posts to `/scan/batch` until the response says the
job is done. No single request ever has to process the whole catalog, progress is
reportable between batches, and closing the tab simply stops the loop.

That makes three states, not two, and they need different controls:

| State | What it means | What is offered |
|---|---|---|
| idle | no job, or the last one finished | Run scan |
| running | this tab is driving batches | progress, Stop scan |
| interrupted | the server still calls the job running, but no tab is driving it | progress, **Continue scan**, Stop scan |

The third state is what a page reload during a scan produces. Treating it as
"running" would show a progress bar that never advances; treating it as "idle"
would silently discard finished work. `useScan` adopts the server's job state on
load, but never while this tab is mid-scan, which would replace live progress
with a stale snapshot.

---

### The product list never loads a WC_Product

A list row needs a title, a type, a score, an issue count and a timestamp. All of
those come from the post, its terms and this plugin's own meta, which `WP_Query`
primes in bulk for the whole page. Hydrating twenty WooCommerce product objects
per page would cost far more and buy nothing, so the list route does not call
`wc_get_product()` at all.

Measured on a 1,087 product catalog, object cache flushed before each request:

| Request | Time | Queries |
|---|---:|---:|
| unfiltered, page 1 | 41 ms | 9 |
| unfiltered, page 25 | 23 ms | 9 |
| unfiltered, last page (55) | 18 ms | 9 |
| filter by health | 20 ms | 9 |
| filter by issue | 14 ms | 9 |
| filter by product type | 15 ms | 11 |
| all three filters combined | 14 ms | 11 |
| search | 18 ms | 9 |
| 100 rows per page | 47 ms | 9 |

The number that matters is the query count, not the milliseconds: it is constant
whether the request is for page 1 or page 55, filtered or not. That is what says
there is no N+1 and that the cost does not grow with the catalog.

### Filter values are defined by the server, never by the client

`health`, `issue`, `type`, `orderby` and `order` are all declared as REST `enum`s
built from live server data: the health buckets, the check registry's ids, and
WooCommerce's registered product types. An unrecognised value is a 400 rather
than a silently ignored parameter. The accepted values are returned with each
page under `filters`, so the browser never has to guess or hard-code them.

Verified: `health=bogus`, `issue=../../etc/passwd`, `type=<script>`,
`orderby=post_password`, `order=DROP` and `per_page=99999` are each rejected with
`rest_invalid_param`, and a search of `' OR 1=1 --` returns no rows.

---

### The detail screen shows the arithmetic

Phase 4's exit criterion is that a score is explainable from the checks beside
it. The detail screen makes that literal: a table listing the starting score,
each deduction with the check that caused it, and a running total that floors at
zero exactly as the scoring rule does. A store owner can add up what is on screen
and arrive at the number displayed.

It also lists what did **not** apply. Without that, "why was my grouped product
not checked for a price?" is unanswerable from the screen, and the product-type
awareness the scanner is built around is invisible. Applicability is derived from
the stored result — whatever neither failed nor passed did not apply — so the
three lists are always consistent with each other and with the score, even if the
product has been edited since the scan.

Issue messages are not stored. They are re-derived from the registry at read
time, so changing the site language changes the wording without a rescan. The
wording is resolved against the product as it is now, which is why the detail
route loads a `WC_Product` where the list route deliberately does not.

Heading structure on the detail screen: `h1` page title (server rendered), `h2`
the product, `h3` each report section, `h4` each severity group. Severity is part
of the document structure, not only a colour.

---

### Phase 8 audit results

Every gate is checked by exit status, not by reading output:

| Gate | Command |
|---|---|
| WordPress Coding Standards | `composer lint` |
| Unit tests, PHP 7.4 / 8.3 / 8.5 | `vendor/bin/phpunit` under each |
| Syntax on the PHP floor | `composer lint:floor` |
| ESLint / Stylelint / Jest | `npm run lint:js` · `lint:css` · `test:js` |
| Production build | `npm run build` |

**Entry points.** Fourteen REST method handlers, every one with a
`permission_callback`. The fifteenth handler WordPress reports is its own
auto-generated namespace index, which every plugin with a REST namespace gets;
it advertises route schemas only and carries no product data. Every declared
parameter has both sanitization and validation, or an `enum`. There are no
`admin-ajax` handlers, no `admin-post` handlers and no forms: all input arrives
through REST and is validated by the schema before a callback runs.

**Static sweep of the plugin's PHP.** No `$_GET` / `$_POST` / `$_REQUEST` access,
no `$wpdb`, no `wp_remote_*` or cURL, no filesystem writes, no `eval`, no
`base64_decode`, no `CREATE TABLE`. Every output statement escapes at the point
of echo.

**No outbound traffic.** With `pre_http_request` intercepting everything, a full
scan of 1,087 products plus the summary and list routes made **zero** HTTP
requests.

**Nothing scans on page load.** Loading both admin screens and calling the
summary and list routes left every product's scan timestamp unchanged and
performed zero plugin meta writes.

**Assets are scoped.** Checked over HTTP across eight admin screens plus the
front page: the bundle loads on this plugin's three views and nowhere else.

**Footprint.** Zero custom tables. Three options, none autoloaded. Roughly 7.3
meta rows and 436 bytes per product. Zero orphaned meta rows.

**No PHP notices.** Verified with `WP_DEBUG` and `WP_DEBUG_LOG` enabled while
exercising every admin screen, every REST route and the front page: no
`debug.log` was written at all.

**With WooCommerce deactivated**: on WordPress 6.5+ the `Requires Plugins`
header prevents activation in the first place. Where that header is not enforced
(WordPress 6.4), or where WooCommerce is active but below 8.0, the plugin
activates and deactivates cleanly, shows the required notice on the Plugins
screen only, loads no assets, registers no admin screen, and every REST route
answers `rest_no_route`.

---

### Internationalization

Text domain `catavio` throughout, 135 strings, every one wrapped.
Verified: no bare user-facing literal anywhere in the JSX, no hard-coded
`aria-label`/`title`/`placeholder`, and every string containing a placeholder
carries a translator comment. Runtime values are never interpolated into a
translated string: issue messages are complete static sentences chosen per
product type, not assembled from fragments.

**Build before extracting.** `npm run i18n` runs build, then `make-pot`, then
`make-json`, in that order, and the order is load-bearing:

- WordPress locates JS translations by `md5()` of the *registered script path*,
  `build/index.js`. `make-json` derives that hash from the file references in the
  `.pot`, so the `.pot` must be generated with `build/` **included**. Excluding it
  produces a valid-looking `.pot` and zero JSON files, and JS translations then
  silently never load. Verified end to end with a throwaway locale: the generated
  filename matched `md5('build/index.js')` exactly.
- Because the `.pot` scans the built bundle, a stale build yields a stale `.pot`.

Two strings share the msgid `%d%%` with different meanings, scan progress and
average health score, so both use `_x()` with a context. Without it a translator
sees one entry and cannot tell which is which.

### Accessibility

Checked by `jest-axe` on every screen and state, plus keyboard interaction tests
driven with `@testing-library/user-event`. The axe setup is itself verified: a
sanity suite asserts it fails on an unlabelled input, an unnamed button and an
image with no alt, so a clean result means something.

Only two axe rules are disabled, both because these tests render the plugin's
subtree rather than a whole page: `region` (WordPress supplies the landmarks)
and `page-has-heading-one` (the `h1` is rendered server side by `Admin\Screen`).

Principles the code holds to:

- **Nothing is carried by colour alone.** Every severity, bucket and score is
  stated in words. Removing every stylesheet removes no meaning. The severity dot
  is CSS, marked `aria-hidden`, and always sits beside its own word.
- **Status is announced once.** `ScoreBadge` carries the spoken form
  ("Health score 65 out of 100, Critical"); where the word is repeated for
  sighted readers it is marked `aria-hidden` rather than read out twice.
- **Scan progress is reported three ways**: a native `<progress>`, a text count,
  and a polite live region, plus `speak()` at the start, end and resume.
- **Focus is never suppressed** — no `outline: none` anywhere. The detail heading
  takes focus on arrival and shows a visible ring.
- Every table has a caption and `scope`d headers; every filter control has a real
  `<label>`; there are no icon-only controls.

---

## 11. Known limitations

**The test suite runs on the React that WordPress ships.** `react` and
`react-dom` are pinned to `^18.3.1` because WordPress 7.1 bundles React 18.3.1.
They were briefly unpinned and resolved to React 19, meaning the jsdom tests ran
against a different React than production; the pin exists so that cannot recur.

**The browser console has never been checked.** The UI is covered by 37 jsdom
render tests and by HTTP checks that each page ships the bundle and its
dependencies, and the source and built bundle contain no `console`, `debugger`,
`alert`, `eval` or `dangerouslySetInnerHTML`. None of that is the same as
loading the page in a browser and watching for runtime errors. Phase 8 asks for
that check and it has not been done.

**Uninstall must remove a known list of options, not a `catavio_` prefix match.**
Anything else on the site sharing that prefix is not this plugin's to delete.
The three options it owns are `catavio_version`, `catavio_scan_state` and `catavio_summary`;
`Repository::meta_keys()` is the matching list for post meta, and a unit test
asserts it covers every key actually written.

**The list shows only products that have been scanned.** A product added after
the last scan does not appear until the next one. The dashboard states when the
last scan ran and how many products it covered, which is where "time to rescan"
belongs; adding an exact unscanned count to the list would cost a query on every
page load to answer a question the dashboard already answers.

**A product deleted while a scan is running may still be scanned.** `WP_Query`
primes the object cache with the rows it has already read, after `the_posts` has
run. A post deleted between the query and the scanner's loop is therefore still
served from that cache and scans successfully. The `skipped` branch in
`Scanner::run_batch()` catches products that fail to load for any other reason,
but it cannot catch this one.

The consequence is bounded: a few `_catavio_*` meta rows pointing at a post that no
longer exists. Nothing reads them, because every query that consumes them joins
against the posts table, and `uninstall.php` removes them by meta key regardless
of whether the post survives. Verified by deliberately deleting a product from
inside `the_posts` mid scan: the scan completed normally, with no fatal.

---

## 12. Verification

How each guarantee in this document is actually checked, so the claims stay
falsifiable rather than aspirational.

| Guarantee | Command | Notes |
|---|---|---|
| WPCS clean | `composer lint` | 302 sniffs, `includes/` waived for file naming only |
| Parses on the PHP floor | `composer lint:floor` | **This is the real PHP 7.4 guard**, see below |
| JS lint | `npm run lint:js` | |
| Style lint | `npm run lint:css` | |
| Bundle ships no framework | inspect `build/index.asset.php` | externals must appear as dependencies |
| Check rules and scoring | `composer test` | 69 tests, no WordPress needed, runs in ~20ms |
| Admin UI renders | `npm run test:js` | 54 jsdom tests: screens, filters, paging, score arithmetic |
| Accessibility | `npm run test:js` | axe on every screen + keyboard traversal; axe itself sanity-checked |
| Translations | `npm run i18n` | build, then make-pot, then make-json, in that order |
| List performance | benchmark on 1,087 products | constant query count by page depth and filter |
| WooCommerce mapping | scan a seeded catalog | fakes cannot prove the real API; see below |

### PHPCompatibility cannot be trusted alone

`phpcompatibility/php-compatibility` 9.3.5 is the latest stable release and dates
from 2019, so it contains no sniffs for any PHP 8 syntax. Verified: a probe file
using the nullsafe operator and `match` passes `--standard=PHPCompatibilityWP
--runtime-set testVersion 7.4-` with zero errors. The sniffs are still useful for
pre-8.0 features, and the ruleset keeps them, but they must not be read as proof
that the code runs on PHP 7.4.

`bin/lint-php-floor.sh` (`composer lint:floor`) parses every plugin file with a
real PHP 7.4 binary instead, which is authoritative for syntax. It rejects the
same probe file that PHPCS accepts. Phase 8 should additionally run the PHPUnit
suite under PHP 7.4, which is the only way to catch PHP 8-only *functions* —
neither check above would flag, say, a call to `str_contains()`.

### Unit tests cannot verify the WooCommerce seam

The check rules read a `ProductSnapshot`, so they are tested without WordPress or
WooCommerce loaded. That is fast and precise, but it proves nothing about whether
the real `WC_Product` behaves as assumed. Two defects found in Phase 3 were
invisible to the unit tests and only appeared when scanning a real catalog:
WooCommerce auto-assigning the default category, and its rejection of
downloadable files outside the approved directories. Every phase that touches
product data must therefore also be exercised against real WooCommerce products,
not only against snapshots.

### The UI tests substitute @wordpress/components

`@wordpress/components` resolves to its TypeScript source under Jest and drags in
its whole dependency tree, and its behaviour is not this plugin's to test. The
test setup replaces it with minimal stand-ins that keep the semantics the tests
assert on: `Button` renders a real `<button>`, so it still has a button role and
an accessible name. What is being tested is this plugin's markup, wording and
states, not the component library.

The `@wordpress/*` packages and `react` are devDependencies purely so Jest can
resolve them. They stay webpack externals: adding them left the built bundle
byte-identical, with the same content hash.

### Nothing here has been checked in a real browser

The admin UI is verified by jsdom render tests and by HTTP checks that the page
delivers the bundle and its dependencies. Neither proves that the page looks
right, that the CSS lays out correctly, or that the browser console is clean.
Phase 8 requires a real browser pass, and it remains outstanding.

### Asset loading is verified over HTTP, not by inspection

Core admin styles are concatenated through `load-styles.php` when
`CONCATENATE_SCRIPTS` is on, so a dependency like `wp-components` never appears
as its own `<link>` tag. Checking for one produces a false negative; check the
`load-styles.php` query string instead.

---

## 13. Extension boundary

`Checks\Registry` exposes the check list through the
`catavio_checks` filter. This is the seam a future Pro version would
use. Free V1 contains no reference to Pro: no locked UI, no disabled controls, no
upsell surface.
