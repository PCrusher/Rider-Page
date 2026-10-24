# Changelog

Versions follow semantic versioning. Patch and minor releases are safe to install on any
site. A major release may contain breaking changes and says so in `manifest.json`.

## 0.3.0

- Editable 404 page: own heading and message, optionally with the latest blog posts. *Admin -> 404 page*.
- Redirects: send an old address to a new one (301 permanent or 302 temporary) so renamed posts keep their visitors and ranking. Real pages are never overridden; loops and system addresses are refused. *Admin -> Redirects*.
- List of addresses that were not found (address and count only, no visitor data, at most 500), with one-click "Create redirect".
- Adds two small tables (`redirects`, `not_found`). Existing content is not touched.

## 0.2.0

- Maintenance mode: switch the page offline for visitors in *Admin → Maintenance*, with your own heading and message. Visitors get a 503 page with `noindex` so search rankings are kept; you stay logged in and see the real site with a reminder bar. Off by default.

## 0.1.1

- A server with PHP older than 8.1 now shows a readable message instead of a blank 500 page.
- Fatal PHP errors are logged to `storage/logs/php-errors.log` and show a short notice.

## 0.1.0

First release.

- One-page portfolio with a sticky menu, sections in a chosen order, and a blog on its own pages.
- Sections: results, partners (with logos and levels), media (YouTube and photos), blog, FAQ, contact.
- 10 colour presets on 4 layouts, with an accent colour override and custom colours.
- Web installer (SQLite or MySQL/MariaDB) and an admin area.
- One-click updates from GitHub releases, with backup, checksum check and automatic rollback.
- English and German page texts. Self-hosted fonts and a click-to-load YouTube player (no data
  goes to Google until a visitor presses play).
