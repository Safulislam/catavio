#!/usr/bin/env bash
#
# Render banner.html into the two PNGs the WordPress.org directory asks for.
#
# The retina file is the render; the small file is that render halved, so the
# two can never drift out of agreement the way two separate exports would.
#
# Chrome is used headless because the banner is text, and text needs a real
# font stack and a real shaper to come out looking like the admin screens do.
#
# Usage: .wordpress-org/build-banners.sh

set -euo pipefail

HERE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

[ -x "$CHROME" ] || { echo "error: Google Chrome not found at $CHROME" >&2; exit 2; }

TMP="$( mktemp -d )"
trap 'rm -rf "$TMP"' EXIT

"$CHROME" \
	--headless \
	--disable-gpu \
	--hide-scrollbars \
	--force-device-scale-factor=1 \
	--default-background-color=00000000 \
	--window-size=1544,500 \
	--screenshot="$TMP/banner-1544x500.png" \
	"file://$HERE/banner.html" >/dev/null 2>&1

[ -f "$TMP/banner-1544x500.png" ] || { echo "error: Chrome produced no screenshot" >&2; exit 1; }

cp "$TMP/banner-1544x500.png" "$HERE/banner-1544x500.png"

# The 772x250 file is the retina render at half size.
cp "$TMP/banner-1544x500.png" "$HERE/banner-772x250.png"
sips --resampleHeightWidth 250 772 "$HERE/banner-772x250.png" >/dev/null

for f in banner-1544x500.png banner-772x250.png; do
	printf '%-22s %s\n' "$f" "$( sips -g pixelWidth -g pixelHeight "$HERE/$f" | awk '/pixel/ {printf "%s ", $2}' )"
done
