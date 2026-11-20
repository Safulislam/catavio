# Catavio — Release Plan: 1.0.4

- **Branch:** `version-1.0.4` (cut from `main` after `version-1.0.3` was merged)
- **Type:** maintenance release: polish, test coverage, and compatibility
- **Status (2026-11-20): folded into 1.1.0.** There was no separate 1.0.4 release;
  this checklist was completed on `version-1.1.0`:
  - 1. Framed panels: listed in the 1.1.0 changelog. Catavio is not yet on WordPress.org,
    so there was no published 1.0.3 zip to compare against. WC tested up to 11.3 is in sync.
  - 2.1 Integration tests: `tests/php/Integration`, run with `composer test:integration`
    and `composer test:integration:multisite`. They need an empty test database; see
    `wp-tests-config.php`.
  - 2.2 Screenshots 1–4 re-captured from the real screens with a demo catalog (1 and 4 again after the dashboard redesign);
    `screenshot-5.png` (Settings) added with its caption. The blueprint is unchanged.
  - 2.3 POT regenerated; German covers all 213 strings.
  - 2.4 Tested with WordPress 7.1.5 and WooCommerce 11.3; PHP 7.4 floor passes.
  - 2.5 `catavio_scanned_statuses` mentioned in the FAQ.
  - 3. Version 1.1.0, changelog, upgrade notice, ARCHITECTURE §10b, gates green,
    release zip built and smoke-tested from the zip.
- **Scope rule:** Free V1 principles in `00-MASTER-PLAN.md` still apply. No scheduled
  scans, bulk fixes, reports, history, AI, telemetry, or Pro locks.

---

## 1. Carried over from the 1.0.3 branch

These commits landed on `version-1.0.3` after the `Release version 1.0.3` commit
(`5f15258`). Confirm whether the published 1.0.3 zip included them. If it did not,
list them in the 1.0.4 changelog.

- [ ] `b74872e` Issue and Product Health tables redesigned as framed panels.
- [ ] `6c66d2e` / `f2e3f8d` "WC tested up to" raised to 11.3 in both `readme.txt` and the
      `catavio.php` header (the two were out of sync before `f2e3f8d`).

## 2. Planned work

### 2.1 Integration tests
`tests/php/Integration/` is empty. All PHP coverage today is unit tests.

- [ ] Run a full scan against real WooCommerce products: simple, variable, grouped,
      external, and downloadable.
- [ ] REST permission checks for `/summary`, `/products`, `/scan`, and `/settings`,
      with and without the required capability.
- [ ] Check that `POST /products/<id>/scan` ("Check again") updates the product's meta and
      marks the dashboard summary as stale.
- [ ] Uninstall on a multisite network removes every option and meta key listed in
      the readme FAQ.

### 2.2 WordPress.org assets
- [ ] Re-capture `screenshot-1..4.png` so they show the framed-panel tables.
- [ ] Add `screenshot-5.png` for the Settings tab (added in 1.0.3, no screenshot yet)
      and its caption in `readme.txt`.
- [ ] Check that the `.wordpress-org/blueprints/blueprint.json` Playground preview
      still opens on the dashboard.

### 2.3 Translations
- [ ] Regenerate the POT (`npm run i18n`) after all string changes.
- [ ] Update `catavio-de_DE.po` for any new or changed strings.
- [ ] Do not add more bundled locales. New languages should come from
      translate.wordpress.org.

### 2.4 Compatibility pass
- [ ] Test against the latest WooCommerce and WordPress releases at release time.
      Update `WC tested up to` (in **both** `readme.txt` and `catavio.php`) and
      `Tested up to`.
- [ ] Confirm the floor still holds: PHP 7.4 (`composer compat`, `composer lint:floor`),
      WP 6.4, and WC 8.0.

### 2.5 Optional, only if time allows
- [ ] Mention the `catavio_scanned_statuses` filter in the "Which products are scanned?"
      FAQ, so store owners know that drafts can be included.

## 3. Release checklist

1. Version bump to `1.0.4` in:
   - `catavio.php` (`Version:` header and `const VERSION`)
   - `package.json` `version`
   - `readme.txt` `Stable tag`
2. `readme.txt`: add `= 1.0.4 =` under both **Changelog** and **Upgrade Notice**.
3. `docs/ARCHITECTURE.md`: add a "Version 1.0.4 additions" section if behaviour changed.
4. Quality gates (all must pass):
   - `composer lint` · `composer compat` · `composer lint:floor` · `composer test`
   - `npm run lint:js` · `npm run lint:css` · `npm run test:js`
5. `npm run i18n` and commit the regenerated language files.
6. `npm run release` (`bin/build-release.sh`), then install the zip on a clean site
   with WooCommerce and smoke-test: scan, product list, product report, Check again,
   Settings, and uninstall.
7. Merge `version-1.0.4` into `main` and tag `v1.0.4`.

## 4. Out of scope for 1.0.4
Scheduled or background scans, bulk fixes, CSV/PDF reports, score history, and AI
suggestions. These are the future Pro items listed in `00-MASTER-PLAN.md`.
