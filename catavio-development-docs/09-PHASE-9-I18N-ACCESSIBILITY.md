# Phase 9 — Internationalization and Accessibility

## Internationalization

Text domain:

`catavio`

Use WordPress translation functions for all user-facing strings.

Examples:

```php
esc_html__( 'Product title is missing.', 'catavio' );
esc_html_e( 'Run Scan', 'catavio' );
```

Do not pass runtime variables as translation strings.

Add translator comments where context is needed.

## Accessibility
Verify:
- Form labels.
- Button labels.
- Table headings.
- Keyboard navigation.
- Focus visibility.
- Screen-reader-friendly status messages.
- Color-independent status communication.
- Meaningful empty states.
- Accessible notices.

## No unnecessary icons
Icons must not be the only way to communicate status.

## Testing
Test with:
- Keyboard only.
- Browser accessibility tooling.
- WordPress admin conventions.

## Exit criteria
All user-facing strings are translation-ready and core workflows are keyboard accessible.
