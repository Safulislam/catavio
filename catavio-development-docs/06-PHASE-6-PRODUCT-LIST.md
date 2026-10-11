# Phase 6 — Product Health List

## Objective
Create a paginated list of scanned products and their health.

## Columns
- Product.
- Type.
- Health.
- Issues.
- Last Checked.
- Actions.

## Example

| Product | Type | Health | Issues | Action |
|---|---|---:|---:|---|
| Nike Air Max | Variable | 61/100 | 4 | View Product |

## Features
- Pagination.
- Health filtering.
- Issue filtering.
- Product type filtering if useful.
- Search where practical.
- Link to standard WooCommerce product editor.
- Link to Catavio detail.

## Performance
Do not load the complete catalog into one page.

Use pagination and efficient queries.

## Security
- Validate all filter parameters.
- Sanitize search/filter input.
- Escape output.
- Enforce capability checks.

## Exit criteria
Users can quickly identify affected products and open the normal product editor.
