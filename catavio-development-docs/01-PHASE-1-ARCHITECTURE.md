# Phase 1 — Architecture

## Objective
Define a small, maintainable architecture before writing feature code.

## Core data flow

WooCommerce Products
→ Scanner
→ Product-Type-Aware Checks
→ Issues
→ Health Score
→ Dashboard / Product List / Product Detail
→ Standard WooCommerce Product Editor

## Suggested structure

```text
catavio/
├── catavio.php
├── readme.txt
├── uninstall.php
├── LICENSE
├── includes/
│   ├── class-plugin.php
│   ├── class-loader.php
│   ├── class-admin.php
│   ├── class-scanner.php
│   ├── class-health-score.php
│   ├── class-product-checks.php
│   ├── class-capabilities.php
│   └── class-assets.php
├── admin/
│   ├── class-dashboard.php
│   ├── class-products.php
│   └── views/
│       ├── dashboard.php
│       ├── products.php
│       └── product-detail.php
├── assets/
│   ├── css/
│   └── js/
├── languages/
├── tests/
└── docs/
```

## Architecture rules
- Use namespace `Catavio`.
- Keep non-namespaced hooks/options/constants prefixed.
- Do not create a custom database table unless a later technical requirement proves necessary.
- Use WooCommerce APIs for product data.
- Keep business logic outside presentation templates.
- Keep scanner checks deterministic.
- Do not scan during normal dashboard page rendering.
- Do not load assets globally.

## Security model
Every privileged operation must have:
1. Capability check.
2. Nonce verification where applicable.
3. Input validation/sanitization.
4. Escaped output.

## Dependency model
The plugin must not fatal-error if WooCommerce is inactive.

## Phase 1 deliverables
- Architecture document.
- File structure.
- Data flow.
- Security model.
- Initial classes/interfaces if justified.
- No unnecessary feature code.

## Exit criteria
- Architecture is understandable.
- No unnecessary framework.
- No external service dependency.
- Scanner can later be implemented without restructuring the plugin.
