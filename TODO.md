# Rider Page: to do and ideas

Collect ideas here. Move an item to **Done** when it ships (note the version).
Priority: `[!]` do soon, `[ ]` open, `[?]` idea to decide on.

## Next

- [!] Get a real install running (olly-wilkins.co.uk) and fix what the first run shows
- [!] Click through installer and admin in a browser: every form, image upload, blog editor, section order, appearance
- [!] Test MySQL/MariaDB (only SQLite is covered by the tests)
- [!] Test the one-click update from a real release (0.1.x to 0.2.0) on the live host
- [ ] Check `https://domain/storage/config.php` is blocked by the installer itself and warn if it is not
- [ ] Admin area in German (the public texts already have English and German)
- [ ] Restore button in *Updates*: restore a backup from `storage/backups` without FTP

## Features

- [ ] Dark variants of the presets, optional automatic switch to the visitor's dark mode
- [ ] Contact form (SMTP settings in the admin, honeypot against spam, no reliance on PHP `mail()`)
- [ ] Logo upload as SVG, cleaned of scripts on upload
- [ ] Sponsor click counts (sponsors like numbers), shown in the admin
- [ ] Media kit: downloadable PDF with results, audience numbers and sponsor packages
- [ ] Writer sections: books, publications, press quotes, events or readings
- [ ] Press and "as seen in" section with logos
- [ ] Race or event calendar (upcoming and past) with dates and links
- [ ] RSS feed for the blog
- [ ] Blog: categories or tags, previous/next links, related posts
- [ ] Gallery lightbox with captions and keyboard navigation
- [ ] Social share image generated per blog post
- [ ] More languages for the public texts (French, Spanish, Dutch, Swedish)
- [ ] Per-section headings and kickers editable in the admin
- [ ] Custom CSS box for people who want small tweaks without touching code
- [ ] Support a user theme folder in `storage/` that overrides templates and survives updates
- [ ] Import and export: all content as one file (move to another host, or backup)
- [ ] Two-factor login for the admin
- [ ] Invite users by email with a link to set their own password (needs mail sending)
- [ ] Users: password reset by email, "last login" column, activity log
- [ ] Per-author profile and byline on blog posts
- [ ] 404 page: suggest the closest matching post or section; separate 404 text per language
- [ ] Redirects: import and export as CSV, wildcard rules (for example a whole old folder)

## Design

- [ ] Visual preview of each preset in *Appearance* (small live thumbnail instead of colour strips)
- [ ] More layouts (a minimal link-in-bio style, a magazine style for writers)
- [ ] Font choice per preset (a few pairings, not free choice)
- [ ] Latin-extended fonts for Central and Eastern European names
- [ ] Accessibility audit (keyboard, screen reader, contrast) of all four layouts

## Performance and SEO

- [ ] Cache rendered pages as files, clear on every admin change
- [ ] WebP versions of uploaded images, `srcset` for responsive sizes
- [ ] Image dimensions in the HTML to avoid layout shift
- [ ] Check structured data with Google's Rich Results Test and fix warnings
- [ ] Sitemap: add last-modified from real edit dates

## Updates and release process

- [ ] Signed releases (checksum is verified today, a signature would also protect against a hijacked release)
- [ ] Update channel setting: stable only, or also pre-releases
- [ ] Test matrix in CI that upgrades from every older release to the new one
- [ ] Emergency `rollback.php` that works even when the app does not start
- [ ] Update notice on the dashboard without waiting for the cache (check in the background)

## Setup and distribution

- [ ] Visual configurator page (pick design, colours and section order, preview, then download settings)
- [ ] Short video and screenshots for the README
- [ ] Hosting notes for common providers (PHP version setting, SQLite vs MySQL, folder permissions)
- [ ] Demo site that runs the template with example content

## Done

- 0.1.0: installer, admin, one-page site, blog, FAQ, 10 presets, updater with backup and rollback, tests, release workflow
- 0.1.1: readable message for old PHP versions, fatal errors logged to `storage/logs/php-errors.log`
- 0.2.0: maintenance mode in the admin
- 0.3.0: editable 404 page, redirects, list of addresses that were not found
- 0.4.0: user levels (admin, editor, author), Users page, post approval
