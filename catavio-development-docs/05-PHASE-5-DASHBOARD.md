# Phase 5 — Dashboard

## Objective
Create the main Catavio admin dashboard.

## Layout

```text
Catavio

Product Health
82%

Healthy: 684
Needs Attention: 421
Critical: 143

Top Issues
Missing featured image    231
Missing short description  87
Missing SKU                54
Missing category           32

[Run Scan]
```

Do not use fake statistics in production.

## Dashboard data
Show:
- Overall health.
- Number of scanned products.
- Healthy products.
- Products needing attention.
- Critical products.
- Top issue types.
- Last scan timestamp.
- Scan status.

## Scan UX
The user starts the scan manually.

For large catalogs:
- Use batches.
- Show progress when practical.
- Handle interruptions safely.

## Empty states
No products:
> No WooCommerce products were found.

No scan yet:
> Run a scan to check your product catalog.

## UI rules
- Use native WordPress admin patterns.
- Do not add a large frontend framework without need.
- Do not use color as the only status indicator.
- Keep labels accessible.
- Avoid marketing-heavy dashboard elements.

## Exit criteria
A store owner can understand catalog status immediately and start a scan without confusion.
