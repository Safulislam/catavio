# Phase 2 — Plugin Foundation

## Objective
Create the safe WordPress/WooCommerce plugin foundation.

## Implement
- Plugin header.
- Constants.
- Namespace.
- Bootstrap.
- Activation hook.
- Deactivation hook.
- Dependency check for WooCommerce.
- Admin menu/submenu.
- Capability handling.
- Asset registration/loading.
- Basic admin page.
- Translation loading.

## Compatibility
- PHP 7.4+.
- Modern supported WordPress.
- Supported WooCommerce versions must be verified during release testing.

## WooCommerce inactive behavior
Show a clear admin notice:

> Catavio requires WooCommerce to be active.

Do not execute WooCommerce-dependent code before WooCommerce is available.

## Admin capability
Use a capability appropriate for managing products. Do not rely only on `is_admin()`.

## Assets
Load CSS/JS only on Catavio screens.

## Coding rules
- WPCS compatible.
- No short ternary.
- No nested ternary.
- Escape all output.
- Sanitize and validate all input.
- Translation-ready strings.
- No unnecessary globals.

## Do not implement
- Scanner.
- AI.
- Pro.
- External APIs.
- Telemetry.
- License system.

## Tests
Verify:
- Plugin activation.
- Plugin deactivation.
- WooCommerce active.
- WooCommerce inactive.
- Unauthorized user access.
- Admin page loading.
- No PHP fatal errors.

## Exit criteria
The empty plugin foundation works cleanly and can safely host the scanner.
