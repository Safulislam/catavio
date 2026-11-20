# Catavio — Development Documentation

This folder contains the step-by-step development specifications for the WordPress.org Free version.

## Development order

1. `00-MASTER-PLAN.md`
2. `01-PHASE-1-ARCHITECTURE.md`
3. `02-PHASE-2-PLUGIN-FOUNDATION.md`
4. `03-PHASE-3-PRODUCT-SCANNER.md`
5. `04-PHASE-4-HEALTH-SCORE.md`
6. `05-PHASE-5-DASHBOARD.md`
7. `06-PHASE-6-PRODUCT-LIST.md`
8. `07-PHASE-7-PRODUCT-DETAIL.md`
9. `08-PHASE-8-TESTING-SECURITY.md`
10. `09-PHASE-9-I18N-ACCESSIBILITY.md`
11. `10-PHASE-10-README-PACKAGING.md`
12. `11-PHASE-11-FINAL-AUDIT.md`

## Implementation plan

`IMPLEMENTATION-PLAN.md` is the concrete build plan derived from all of the files
above: React admin on WordPress core's bundled React, Composer PSR-4 autoloading,
REST data layer, file structure, data model, check matrix, and the phase-by-phase
execution order. Read it alongside the phase docs.

Use the files sequentially with Claude Code. Complete and test one phase before moving to the next.

## Release plans

- `12-RELEASE-1.0.4-PLAN.md` — next maintenance release: integration tests,
  refreshed WordPress.org screenshots, translations, compatibility pass, release checklist.

## Pro plan (Free → Pro, SEO · AEO · AI)

Start with `pro/00-PRO-MASTER-PLAN.md`: decisions, Free/Pro boundary, feature matrix,
release map. Then, in order:

1. `pro/01-FREE-1.1-EXTENSIBILITY.md` — hooks, snapshot extension, UI slots, new checks, read-only abilities (ships in Free)
2. `pro/02-PRO-FOUNDATION.md` — `catavio-pro` add-on, licensing, data tables, fixes engine with undo
3. `pro/03-AUTOMATION-HISTORY.md` — scheduled scans, history, digest, export, rules, WP-CLI, template fixes
4. `pro/04-SEO-PACK.md` — SEO plugin adapters, SEO checks, structured-data validation
5. `pro/05-AEO-PACK.md` — answer-engine / AI-agent readiness checks, AI crawler access, product FAQ
6. `pro/06-AI-PACK.md` — AI drafts via WordPress core AI Client, guardrails, bulk AI, agent abilities
7. `pro/07-QA-LAUNCH.md` — compatibility matrix, budgets, security, privacy, launch gates
