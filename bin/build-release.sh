#!/usr/bin/env bash
#
# Build the WordPress.org release archive.
#
# Why this exists: the distributable differs from the working tree in three ways
# that are easy to get wrong by hand, and expensive to get wrong in public.
#
#   1. build/ is in .gitignore, so a clone does not have it. It must be compiled
#      and it must ship, or the admin screens render nothing.
#   2. vendor/ in the working tree holds the dev dependencies — PHPUnit, PHPCS
#      and WPCS, some 26MB of them. The release needs the autoloader and nothing
#      else, so a production tree is installed into a staging copy rather than
#      over the top of the one being worked in.
#   3. .distignore lists what must not ship. Nothing enforced it before this
#      script, because nothing read the file.
#
# package.json and src/ do ship, deliberately: WordPress.org asks for the source
# of compiled assets and for the tooling that builds it.
#
# Everything happens in a staging directory. The working tree is never modified.
#
# Usage:
#   bin/build-release.sh          build dist/catavio.zip
#   bin/build-release.sh --keep   also leave the unpacked tree for inspection

set -euo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
SLUG="catavio"
DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"
KEEP=0

if [ "${1:-}" = "--keep" ]; then
	KEEP=1
fi

fail() {
	echo "error: $1" >&2
	exit 1
}

# --- Version must agree in all three places it is written ------------------

header_version="$( sed -n 's/^ \* Version: *\([0-9][^ ]*\) *$/\1/p' "$ROOT/$SLUG.php" | head -1 )"
const_version="$( sed -n "s/^const VERSION = '\([^']*\)';$/\1/p" "$ROOT/$SLUG.php" | head -1 )"
stable_tag="$( sed -n 's/^Stable tag: *\(.*[^ ]\) *$/\1/p' "$ROOT/readme.txt" | head -1 )"

[ -n "$header_version" ] || fail "could not read Version from $SLUG.php"
[ -n "$const_version" ] || fail "could not read const VERSION from $SLUG.php"
[ -n "$stable_tag" ] || fail "could not read Stable tag from readme.txt"

if [ "$header_version" != "$const_version" ]; then
	fail "plugin header says $header_version but const VERSION says $const_version"
fi

if [ "$header_version" != "$stable_tag" ]; then
	fail "plugin header says $header_version but readme.txt Stable tag says $stable_tag"
fi

echo "Version    $header_version"

# --- Everything WordPress.org refuses a plugin for, checked before building --

for required in readme.txt LICENSE uninstall.php; do
	[ -f "$ROOT/$required" ] || fail "$required is missing"
done

# --- Compile the admin bundle ----------------------------------------------

echo "Building   admin bundle"
( cd "$ROOT" && npm run build --silent >/dev/null )

for artifact in build/index.js build/index.asset.php build/style-index.css build/style-index-rtl.css; do
	[ -f "$ROOT/$artifact" ] || fail "$artifact was not produced by the build"
done

# --- Stage ------------------------------------------------------------------

rm -rf "$STAGE"
mkdir -p "$STAGE"

echo "Staging    $STAGE"
rsync -a --delete \
	--exclude-from="$ROOT/.distignore" \
	--exclude '/dist' \
	--exclude '/vendor' \
	"$ROOT/" "$STAGE/"

# --- Production autoloader, built in the staging copy -----------------------

#
# composer.json is staged by the rsync above, because it ships: the Plugin
# Directory asks to see the Composer manifest of any plugin built with
# Composer. Only the lock file is borrowed for the install and then removed.
#
echo "Composer   installing without dev dependencies"
[ -f "$STAGE/composer.json" ] || fail "composer.json was not staged; it must ship, so check .distignore"
cp "$ROOT/composer.lock" "$STAGE/"
( cd "$STAGE" && composer install --no-dev --optimize-autoloader --quiet --no-interaction )
rm -f "$STAGE/composer.lock"

[ -f "$STAGE/vendor/autoload.php" ] || fail "the production autoloader was not installed"

# --- Refuse to ship anything that should not be there -----------------------

for forbidden in node_modules tests .git .github composer.lock phpunit.xml.dist phpcs.xml.dist vendor/bin; do
	if [ -e "$STAGE/$forbidden" ]; then
		fail "$forbidden was staged; check .distignore"
	fi
done

if find "$STAGE" -name '.DS_Store' -o -name '*.cache' -o -name '.env*' | grep -q .; then
	fail "development or temporary files were staged; check .distignore"
fi

# --- Archive ----------------------------------------------------------------

ZIP="$DIST/$SLUG.zip"
rm -f "$ZIP"

( cd "$DIST" && zip -rq "$SLUG.zip" "$SLUG" -x '*.DS_Store' )

files="$( find "$STAGE" -type f | wc -l | tr -d ' ' )"
size="$( du -h "$ZIP" | cut -f1 | tr -d ' ' )"

if [ "$KEEP" -eq 0 ]; then
	rm -rf "$STAGE"
fi

echo
echo "OK         $ZIP"
echo "           $files files, $size"
echo
echo "Next: unpack into a clean WordPress install and test before uploading to SVN."
echo "The directory assets in .wordpress-org/ are deliberately not in this archive."
echo "They belong in the SVN /assets directory, alongside trunk/ and tags/."
