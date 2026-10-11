# Phase 4 — Health Score

## Objective
Create a transparent Product Health Score.

## Important positioning
The score measures product information completeness/configuration checks.

It is NOT:
- Sales score.
- Conversion score.
- SEO ranking score.
- Revenue prediction.

## Suggested calculation

Start at 100.

- Critical issue: -20.
- Warning: -10.
- Minor issue: -5.

Minimum score: 0.

The exact scoring rules must be documented in code.

## Example

100
- 20 missing description
- 10 missing short description
- 10 missing featured image

= 60/100

## Product-type awareness
Only applicable checks should affect the score.

## Output
Return:
- Numeric score.
- Issue count.
- Severity counts.
- Passed checks.
- Failed checks.

## Tests
Test:
- 100/100 complete product.
- Single critical issue.
- Multiple warnings.
- Score below zero.
- Product-type-specific checks.

## Exit criteria
Every score is reproducible and explainable from visible checks.
