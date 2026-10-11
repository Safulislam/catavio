# Phase 3 — Product Scanner

## Objective
Build the deterministic product scanner.

## Supported product types
- Simple.
- Variable.
- Grouped.
- External/Affiliate.
- Virtual.
- Downloadable.

## Checks

### Required/basic checks
1. Product title.
2. Product description.
3. Short description.
4. Featured image.
5. Price where appropriate.
6. SKU.
7. Product category.
8. Stock status where applicable.
9. Attributes where applicable.
10. Product image ALT text.

## Product-type awareness
Do not apply every rule to every product type.

Examples:
- Missing attributes should not automatically be an issue for every simple product.
- External products have different pricing behavior.
- Virtual products should not be judged on shipping configuration.
- Variable products need variation-aware handling.

## Issue model

Each issue should contain enough information for the UI to explain:
- Check ID.
- Severity.
- Message.
- Product ID.
- Optional supporting data.

Suggested severities:
- Critical.
- Warning.
- Info.

## Scanner behavior
- Batch products.
- Avoid full-catalog processing in one request.
- Handle deleted/invalid products safely.
- Avoid unnecessary database writes.
- Do not duplicate complete product data.
- Do not scan automatically on dashboard page load.

## Initial scan
Manual action:

`Run Scan`

## No AI
All results must be deterministic and explainable.

## Tests
Create tests for:
- Complete product.
- Missing title.
- Missing description.
- Missing short description.
- Missing featured image.
- Missing SKU.
- Missing category.
- Variable product.
- Grouped product.
- External product.
- Virtual product.
- Downloadable product.

## Exit criteria
The scanner can safely analyze a representative WooCommerce catalog and return structured issues.
