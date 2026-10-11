# Catavio — Master Development Plan

## Product
**Name:** Catavio  
**Slug:** `catavio`  
**Text Domain:** `catavio`  
**Namespace:** `Catavio`

## Goal
Build a lightweight, production-quality WooCommerce product quality checker for WordPress.org.

Core question:

> Are my WooCommerce products properly prepared?

## Free V1 Principles
- Real standalone value.
- No trialware.
- No artificial product/scan limits.
- No license system.
- No telemetry.
- No external API.
- No AI.
- No Pro locks or disabled promotional features.
- Local processing only.
- WordPress-native admin UX.
- WooCommerce-aware checks.
- Secure, performant, translation-ready.

## Development phases

| Phase | File | Goal |
|---|---|---|
| 1 | `01-PHASE-1-ARCHITECTURE.md` | Architecture and technical foundation |
| 2 | `02-PHASE-2-PLUGIN-FOUNDATION.md` | Bootstrap, dependency checks, admin foundation |
| 3 | `03-PHASE-3-PRODUCT-SCANNER.md` | Scanner and product checks |
| 4 | `04-PHASE-4-HEALTH-SCORE.md` | Transparent health scoring |
| 5 | `05-PHASE-5-DASHBOARD.md` | Dashboard and scan UI |
| 6 | `06-PHASE-6-PRODUCT-LIST.md` | Product health list and filters |
| 7 | `07-PHASE-7-PRODUCT-DETAIL.md` | Product detail health view |
| 8 | `08-PHASE-8-TESTING-SECURITY.md` | Tests, security and performance |
| 9 | `09-PHASE-9-I18N-ACCESSIBILITY.md` | Translation and accessibility |
| 10 | `10-PHASE-10-README-PACKAGING.md` | WordPress.org packaging |
| 11 | `11-PHASE-11-FINAL-AUDIT.md` | Final submission audit |

## Future Pro boundary
Future Pro may add advanced checks, bulk fixes, scheduled scans, reports, AI assistance and historical analysis. Do not implement those in Free V1.

## Completion rule
Do not claim WordPress.org approval. Approval is determined by the WordPress.org review process.
