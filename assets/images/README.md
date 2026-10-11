# WordPress.org directory assets

These files are **not** part of the plugin. They belong in the top level
`assets/` directory of the WordPress.org SVN repository, beside `trunk/` and
`tags/` — never inside them. Keeping them out of the plugin is what keeps the
download small, which is why `.distignore` excludes this whole folder.

| File | Where it appears |
|---|---|
| `icon-128x128.png` | Search results and the plugin installer |
| `icon-256x256.png` | High-DPI version of the above |
| `icon.svg` | Preferred by the directory where supported; the PNGs remain the fallback |
| `banner-772x250.png` | Header of the plugin's directory page |
| `banner-1544x500.png` | High-DPI version of the banner, which only works if the 772x250 file is present too |
| `screenshot-1.png` … `screenshot-4.png` | The Screenshots section of `readme.txt`, in caption order |
| `banner.html` | Source of both banners. Not uploaded |
| `build-banners.sh` | Renders that source into both banners. Not uploaded |

## Rebuilding them

The artwork is vector. `icon.svg` is the source for both icon PNGs; re-export at
256 and 128 if it changes. The icon carries no wordmark, so a change of plugin
name does not oblige a re-export.

The banners do carry the name, and `banner.html` is where it is written. Run
`.wordpress-org/build-banners.sh` to rebuild both PNGs from it. The layout is
authored once at 772x250 and rendered at a 2x scale; the small file is then that
render halved, so `banner-1544x500.png` is exactly twice the 772x250 layout by
construction rather than by careful hand-matching.

The screenshots are not mock-ups. They are the plugin's own compiled bundle,
mounted in a browser against WordPress's real admin stylesheets and script
bundles, with a demo catalog standing in for the REST responses. Re-render them
after any UI change so the captions and the screens continue to agree.
