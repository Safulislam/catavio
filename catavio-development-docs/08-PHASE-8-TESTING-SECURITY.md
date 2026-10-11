# Phase 8 — Testing, Security and Performance

## Objective
Perform a production-level technical audit.

## Automated testing

### PHPUnit
Test:
- Scanner.
- Checks.
- Health score.
- Product types.
- Permissions.
- Dependency behavior.

### PHPCS
Run WordPress Coding Standards.

Fix all applicable violations rather than suppressing them.

### PHP
Run syntax checks.

### JavaScript
If JS exists:
- lint.
- build.
- verify no console errors.

## Security audit

Check every:
- Admin action.
- AJAX action.
- Form.
- GET parameter.
- POST parameter.
- REST route if any.
- Database query.
- Output.

Verify:
- Capability checks.
- Nonces.
- Sanitization.
- Validation.
- Escaping.
- Prepared queries where required.

## Performance audit
Verify:
- No dashboard full-catalog scan.
- No global assets.
- No unnecessary database tables.
- No N+1 behavior where practical.
- Batch scanning.
- Pagination.
- No external HTTP requests.

## Failure tests
Test:
- WooCommerce inactive.
- No products.
- Deleted product during scan.
- Invalid product ID.
- Unauthorized access.
- Interrupted scan.
- Large catalog.

## Exit criteria
No known security, performance, PHP, WPCS or functional blocker remains.
