#!/usr/bin/env bash
#
# Lint every plugin PHP file against the minimum supported PHP version.
#
# Why this exists: the latest stable phpcompatibility/php-compatibility (9.3.5,
# 2019) predates PHP 8 and has no sniffs for PHP 8 syntax, so PHPCS alone will
# silently accept `match`, `?->`, `mixed` and friends when testVersion is 7.4.
# Parsing the files with a real PHP 7.4 binary is the authoritative check.
#
# Usage:
#   bin/lint-php-floor.sh [path-to-php-7.4-binary]
#
# Defaults to the MAMP build when present.

set -euo pipefail

PHP_BIN="${1:-${CATAVIO_PHP_FLOOR:-/Applications/MAMP/bin/php/php7.4.33/bin/php}}"

if [ ! -x "$PHP_BIN" ]; then
	echo "error: PHP binary not found or not executable: $PHP_BIN" >&2
	echo "pass one as an argument, or set CATAVIO_PHP_FLOOR" >&2
	exit 2
fi

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
VERSION="$( "$PHP_BIN" -r 'echo PHP_VERSION;' )"

echo "Linting against PHP ${VERSION}"

status=0
count=0

while IFS= read -r file; do
	count=$(( count + 1 ))
	if ! output="$( "$PHP_BIN" -l "$file" 2>&1 )"; then
		echo "FAIL  ${file#"$ROOT"/}"
		echo "      $output"
		status=1
	fi
done < <( find "$ROOT" -name '*.php' \
	-not -path "$ROOT/vendor/*" \
	-not -path "$ROOT/node_modules/*" \
	-not -path "$ROOT/dist/*" \
	| sort )

if [ "$status" -eq 0 ]; then
	echo "OK    ${count} files parse under PHP ${VERSION}"
fi

exit "$status"
