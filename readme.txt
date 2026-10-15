=== Catavio – Catalog Health for WooCommerce ===
Contributors: saiful09
Tags: woocommerce, product catalog, product management, product editor, product quality
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan your WooCommerce catalog for missing product information, review a clear health report, and open the product editor to fix each issue.

== Description ==

Catavio is a catalog health tool for WooCommerce. It reads your products, finds missing or incomplete information, and shows the results in one admin screen — Dashboard and Product Health — so you can see what needs work and open the standard WooCommerce editor to fix it.

It does not rewrite titles, prices, descriptions, images, stock, or any other product field. It reports. You edit.

= Who it is for =

Store owners and catalog managers who need a reliable picture of product completeness: missing titles, empty descriptions, products with no price, uncategorised items, missing images, incomplete variable products, downloadable products with no files, and external products with no URL.

If you already know a few products are unfinished, Catavio is how you find the rest without opening them one by one.

= How it works =

1. Open **Catalog Health** in the WordPress admin menu (placed just below WooCommerce).
2. Run a scan. Products are processed in batches of 50. You can stop a scan and continue it later.
3. Use the **Dashboard** for catalog-wide figures: average completeness, how products split between healthy / needs attention / critical, and the issues affecting the most products.
4. Open **Product Health** to search and filter the catalog, then open a product’s report.
5. Follow the **Edit product** link into the normal WooCommerce product editor. After you save a product, run a scan again to refresh the report.

Opening the Dashboard never starts a scan and never walks the catalog. Figures come from the last scan that finished. A cancelled or abandoned scan does not overwrite those figures.

= The three screens =

**Dashboard** — Average completeness for the scanned catalog, a breakdown of healthy / needs attention / critical products, the most common issues (up to ten), and the scan controls. Every number is from a completed scan, not an estimate.

**Product Health** — A paginated list of scanned products. Search by name, then narrow by health group, issue type, and product type. Filters stay in the address bar, so a view can be bookmarked and the browser back button works as expected. Each row links to the product report and to the WooCommerce editor.

**Product report** — One product at a time: the health score, every issue with its severity, the arithmetic behind the score (starting at 100, then each deduction), checks that passed, and checks that were skipped because they do not apply to that product type. The only action is a link to the product editor.

= What it checks =

Thirteen rules. A rule that does not apply to a product type is skipped, not failed — so a simple product is never marked down for having no variations, a grouped product is not expected to have its own price, and alt text is only checked when a featured image exists.

**Critical** — information a customer typically cannot do without:

* Product title
* Description
* Price (skipped for grouped products; for variable products, at least one variation must have a price)
* Variations, for variable products
* Variation attributes, for variable products (at least one attribute marked “used for variations”)
* Product URL, for external / affiliate products
* Downloadable files, for downloadable products

**Warning** — information that is usually expected on a complete listing:

* Short description
* SKU
* Product category (WooCommerce’s default “Uncategorized” category counts as missing a real category)
* Featured image
* Stock configuration (skipped for external and grouped products; if stock management is on, a quantity must be set)

**Info** — accessibility of the listing image:

* Featured image alt text (only when a featured image is present, so a missing image is not counted twice)

Empty markup does not count as a description. A description that is only an empty paragraph or a non-breaking space is treated as missing.

= The health score =

Every product starts at **100**. Points are subtracted only for issues that apply to that product:

* −20 for each critical issue
* −10 for each warning
* −5 for each informational finding
* The score never goes below 0

Example: missing description (−20), missing short description (−10), missing featured image (−10) → **60**.

The product report shows this arithmetic in full. The score measures how completely product information and configuration are filled in. It is not a sales, conversion, SEO, or revenue score.

Products are grouped by what the scan found, not by a score cut-off:

* **Critical** — at least one critical issue
* **Needs attention** — at least one issue, none of them critical
* **Healthy** — nothing was found

= Scanning =

Scans run only when you start one. Nothing runs on the storefront, and nothing runs when an admin page loads.

* Published and private products are included. Drafts and other statuses are not, unless a developer changes that.
* Work is done in batches (50 products by default) so large catalogs do not lock the admin on one long request.
* Progress is shown as a count, a percentage, and a progress bar, and is announced to screen readers.
* If a scan is interrupted, you can continue it or stop it and start again. Stopping does not replace the last completed dashboard summary.

Results are stored as plugin options and per-product meta. Opening Catalog Health reads that stored data.

= What Catavio never does =

* It never edits product data.
* It never replaces the WooCommerce product editor.
* It never adds storefront output, checkout fields, or customer-facing widgets.
* It never phones home, collects analytics, or requires an account or licence key.
* It never shows upgrade prompts, review nags, dashboard widgets, or notices on unrelated admin pages.

The only notice it can show is a requirements message on the Plugins screen (WooCommerce missing or too old, or PHP / WordPress below the versions above). That notice is dismissible and does not appear elsewhere.

= Privacy =

No external requests. No tracking. No account. Everything stays in your WordPress database.

On delete, the plugin removes its three options and its six per-product meta keys on every site in a multisite network. Deactivating leaves scan results in place so reactivation does not force a new scan.

= Accessibility =

Colour is never the only signal. Severity and health groups are also written in words. Scan progress is exposed to assistive technology. Admin screens are checked with axe in the plugin’s test suite.

= Compatibility =

* WordPress 6.4 or newer, PHP 7.4 or newer, WooCommerce 8.0 or newer
* Simple, variable, grouped, external, virtual, and downloadable products
* High-Performance Order Storage (HPOS) — the plugin reads products only and does not touch orders
* Cart and checkout blocks — compatibility is declared; the plugin does not alter checkout
* Translation-ready (text domain `catavio`, POT file included)
* RTL stylesheet shipped for the admin UI

Catavio is an independent plugin, developed and maintained by Saiful Islam. It is not affiliated with, endorsed by, sponsored by, or otherwise connected to WooCommerce, Automattic Inc., or the WordPress Foundation. WooCommerce and WordPress are trademarks of their respective owners.

== Installation ==

1. Install and activate WooCommerce 8.0 or newer.
2. Install Catavio from Plugins → Add New, or upload the plugin folder to `/wp-content/plugins/catavio`.
3. Activate Catavio from the Plugins screen.
4. In the admin menu, open **Catalog Health** and run your first scan.
5. Use the Dashboard for the catalog overview, then Product Health to work through individual products.

WooCommerce is declared as a required plugin. On WordPress 6.5 and newer, Catavio cannot be activated until WooCommerce is active, and the installer states the dependency.

WordPress 6.4 does not enforce that header, and the header does not check which WooCommerce version is running. Catavio therefore also checks itself: if WooCommerce is missing or older than 8.0, or PHP or WordPress is below the versions listed above, it activates without a fatal error, stays inactive, and explains why in a dismissible notice on the Plugins screen only.

== Frequently Asked Questions ==

= Does it change my products? =

No. It only reads product data. It writes its own scan results (options and post meta) and never updates a title, price, description, image, SKU, stock field, or any other product field. Fixes happen in the WooCommerce product editor.

= Will it slow down my store? =

Nothing from this plugin runs on the front end. Scans run only when you start them, in batches, in the admin. The Dashboard reads a stored summary instead of querying every product on each page load.

= Who can use Catalog Health? =

Anyone who can manage WooCommerce. The plugin uses `manage_woocommerce` when that capability exists, then `edit_products`, then `manage_options`. Developers can change the required capability with the `catavio_capability` filter.

= Which products are scanned? =

Published and private products. Drafts, pending, and trash are not included by default.

= Does a grouped or variable product fail checks that do not apply? =

No. Grouped products are not judged on their own price or stock. Variable products are checked for variations, variation attributes, and whether any variation has a price. Simple products are not checked for variations. External products need a product URL and are not judged on stock. Downloadable files are checked only when the product is downloadable. Featured image alt text is checked only when a featured image exists.

= Why is a product in “Uncategorized” flagged? =

WooCommerce assigns its default category to products saved without one, so an empty category list almost never happens. Catavio treats that default bucket as “not assigned to a real category,” which is what store owners usually mean.

= Why did my score not change after I edited a product? =

Results are from the last completed scan. Edit the product, then run a scan again so the stored report and dashboard figures update.

= What if a scan is interrupted? =

You can continue from where it stopped, or stop the job and start a new scan. An unfinished scan does not replace the last completed dashboard summary.

= Does it work with High-Performance Order Storage? =

Yes. The plugin reads product data only and never touches orders. Compatibility with HPOS and with cart and checkout blocks is declared.

= Does it support variable, grouped, external, and downloadable products? =

Yes. Checks are product-type aware. See “What it checks” in the description.

= Is it translation ready? =

Yes. PHP and JavaScript strings use the `catavio` text domain. A POT file ships in `/languages`, together with a complete German (Germany) translation that loads automatically on sites running in German. English is the source language, so no English translation file is needed.

= What is stored, and what is removed on uninstall? =

Stored: plugin version, catalog summary, scan job state, and six meta keys per scanned product (`_catavio_issues`, `_catavio_passed`, `_catavio_issue_code`, `_catavio_score`, `_catavio_severity`, `_catavio_scanned_at`).

Deleting the plugin removes all of that on every site in a multisite network. Deactivating does not.

= Does it add clutter to wp-admin? =

One menu item: **Catalog Health**. No extra submenu duplicates, no dashboard widgets, no upsells, no review requests. Assets load only on that screen.

== Screenshots ==

1. Dashboard — average completeness, healthy / needs attention / critical split, and the issues affecting the most products.
2. Product Health — filter by health, issue type, and product type; every row links to the product editor.
3. Product report — every issue found and the arithmetic behind the health score.
4. A scan in progress — running count, percentage, and the option to stop.

== Changelog ==

= 1.0.2 =
* Added a complete German (Germany) translation; translations now load from the bundled /languages directory.
* Refreshed the translation template (POT) to include the first-run guide strings.

= 1.0.1 =
* Added a first-run guide for new users with products that have not been scanned.
* Added a one-time dashboard handoff after first activation; the guide is optional and can be hidden.
* Polished the dashboard section tabs with a smooth active-state transition.

= 1.0.0 =
* Initial release: catalog scan, thirteen product-type-aware checks, transparent health score, Dashboard and Product Health screens.

== Upgrade Notice ==

= 1.0.2 =
Adds a German translation that loads automatically on sites running in German.

= 1.0.1 =
Adds an optional first-run guide and smoother dashboard section tabs.
