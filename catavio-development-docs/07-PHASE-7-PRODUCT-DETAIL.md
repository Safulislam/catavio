# Phase 7 — Product Health Detail

## Objective
Provide a clear health report for one product.

## Example

```text
Nike Air Max 270

Product Health
61/100

Issues

CRITICAL
Missing short description

WARNING
SKU is missing

WARNING
Featured image ALT text is missing

Passed

Product title
Description
Price
Category
Stock configuration

[Edit Product]
```

## Detail data
Show:
- Product name.
- Product type.
- Health score.
- Critical issues.
- Warnings.
- Informational findings.
- Passed checks.
- Last checked time.

## Actions
Primary action:
`Edit Product`

The action must open the normal WooCommerce product editor.

Do not create a replacement product editor.

## Accessibility
- Use headings correctly.
- Use text labels with status.
- Do not depend only on red/yellow/green.
- Ensure keyboard navigation.

## Exit criteria
A user can understand exactly why a product received its score and knows where to fix it.
