# Phase 1 — Free 1.1.0: Extensibility Foundation

**Repo:** `catavio` (this one). **Ships to:** WordPress.org.
**Goal:** give Free more standalone value and add the public seams that Pro (or any
developer) needs. Free still contains **no reference to Pro**.

> **Status (2026-11-20): implemented on branch `version-1.1.0`.** The authoritative
> hook reference is `docs/ARCHITECTURE.md` §13. Differences from this plan:
>
> - **Tabs:** PHP filter `catavio_admin_views` (tabs are rendered server side) plus
>   JS filter `catavio.views` (view id → component). There is no `catavio.tabs`.
> - **Extra filter:** `catavio_rest_product_row` for list rows, separate from the
>   report filter.
> - **All three new checks are optional (off by default)**, not just GTIN. Otherwise
>   every existing store's scores would drop on the first scan after updating.
>   Settings now stores `enabled_checks` next to `disabled_checks`.
> - **GTIN** applies to simple and external products only. Variation-level GTINs are
>   left for later, because checking variations one by one would multiply scan cost.
>   The field exists from WooCommerce **9.1** (not 9.2).
> - **Gallery** check also skips virtual products.
> - **Groups:** content, media, commerce, identifiers, structure, and other. Settings
>   is grouped by topic. The product report stays grouped by severity (worst
>   first), and each issue carries `group` / `groupLabel`.
> - Response filters can **add** keys but never change Catavio's own keys.
>
> Verified: 144 PHP and 82 JS tests, PHPCS, PHP 7.4 compatibility, and a live run on
> the dev site (WP 7.1.5, WC 11.3.0, 18 products): actions fire, abilities execute,
> and abilities refuse logged-out users.

---

## 1. PHP hooks

Add these, document each one in `docs/ARCHITECTURE.md` §13, and unit-test each.

### Actions
| Hook | Where | Args |
|---|---|---|
| `catavio_scan_started` | `Scanner::start()` | `$total` |
| `catavio_product_scanned` | `Scanner::scan_snapshot()`, after storage | `$product_id, Result $result` |
| `catavio_scan_completed` | when the last batch finishes and the summary is saved | `array $summary` |
| `catavio_scan_cancelled` | `Scanner::cancel()` | — |
| `catavio_settings_saved` | `Settings::set_disabled_checks()` | `$disabled` |

### Filters
| Hook | Purpose |
|---|---|
| `catavio_snapshot_data` | Lets extensions attach extra data to a snapshot (see §2). |
| `catavio_severity_penalties` | Lets extensions adjust the critical/warning/info penalties (defaults unchanged). |
| `catavio_check_groups` | Group checks for the UI and for settings (see §3). |
| `catavio_rest_product_response` | Add fields to `/products` and `/products/<id>` responses. |
| `catavio_rest_summary_response` | Add fields to `/summary`. |
| `catavio_admin_bootstrap_data` | Add data to the inline bootstrap object in `Admin/Screen.php`. |

Rules: hooks fire **only in admin/REST/cron contexts**, never on the front end, and
every filter result is re-validated (for example, penalties are clamped to 0–100).

## 2. Snapshot extension bag

`ProductSnapshot` stays a fast, plain value object. Add:

```php
/** @var array<string, mixed> Extension data, keyed by extension slug. */
public $extra = array();

public function get_extra( $key, $default = null );
```

`ProductSnapshot::from_product()` ends with:

```php
$snapshot->extra = (array) apply_filters( 'catavio_snapshot_data', array(), $product, $snapshot );
```

Extensions read the data they need **once per product** here, so checks stay pure
functions of the snapshot. Unit tests keep using `SnapshotFactory`.

## 3. Check groups

Add an optional `get_group()` to checks through a new `GroupedCheckInterface`. This is
backward compatible: `CheckInterface` itself does not change.

Built-in groups: `content`, `media`, `pricing_stock`, `structure`. Extensions register
more groups via `catavio_check_groups` (Pro adds `seo` and `aeo`).

UI changes: Settings shows checks grouped under headings, and the product report groups
issues the same way.

## 4. JavaScript extension points (`@wordpress/hooks`)

Add `@wordpress/hooks` as a dependency. It is a core script handle, so nothing extra
ships in the bundle.

| Filter | Used for |
|---|---|
| `catavio.tabs` | Add top-level tabs (Pro: "SEO & AI", "History") |
| `catavio.productReport.issueActions` | Add buttons beside an issue (Pro: "Fix", "Fix with AI") |
| `catavio.productReport.panels` | Add panels under the report |
| `catavio.productList.columns` | Add list columns |
| `catavio.settings.sections` | Add settings sections |
| `catavio.dashboard.cards` | Add dashboard cards |

Each filter receives and returns a plain array of `{ key, label, render }`. Free
renders whatever comes back inside an error boundary, so a broken extension cannot
break the Free UI.

## 5. New Free checks (catalog completeness)

| ID | Applies to | Severity | Rule |
|---|---|---|---|
| `gtin` | simple, variation parents (per variation) | info | `global_unique_id` empty. Only active when WooCommerce ≥ 9.1. Off by default in Settings, because many shops sell own-brand goods without GTINs. |
| `brand` | all except grouped | info | No term in the core `product_brand` taxonomy. Only active when that taxonomy exists. |
| `gallery_images` | simple, variable, external | info | Fewer than 1 gallery image beyond the featured image. |

The FAQ count goes from "thirteen" to "sixteen". Update the readme, the POT and the
German translation.

## 6. Read-only Abilities (agent access, local only)

Guarded by `function_exists( 'wp_register_ability' )`, so nothing happens below
WordPress 6.9. Register category `catavio` and:

| Ability | Returns |
|---|---|
| `catavio/get-catalog-health` | the summary (average, buckets, top issues, staleness) |
| `catavio/list-products-by-health` | paged products filtered by health, issue or type |
| `catavio/get-product-health` | one product's issues, score and explanation |

- Permission callback: `Capabilities::current_user_can_manage()`.
- Input and output JSON schemas mirror the REST schemas.
- **Read-only.** No scan, no writes.

This lets a store owner's AI agent (connected through the MCP Adapter plugin) ask "which
products need work?". Free makes no external call to support it.

## 7. Done when

- [ ] All hooks are documented in ARCHITECTURE §13, with unit tests.
- [ ] A throwaway test plugin in `tests/fixtures/` adds a check, a snapshot field, a tab
      and an issue action, and all four render or score correctly.
- [ ] Free behaves exactly as in 1.0.4 when no extension is active (snapshot diff test).
- [ ] Abilities are listed by `wp_get_abilities()` on WordPress 6.9+ and absent below it.
- [ ] Plugin Check passes, with no new warnings.
- [ ] Changelog: "Developer hooks, three new optional checks, read-only abilities".
