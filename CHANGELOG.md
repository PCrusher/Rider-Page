# Changelog

Versions follow semantic versioning. Patch and minor releases are safe to install on any
site. A major release may contain breaking changes and says so in `manifest.json`.

## 0.7.0

- Section headings and small labels are editable, and each can be switched off (*Admin -> Sections*, "Headings and labels"). The heading also names the section in the menu.
- Partner levels (title partner, partners, supporters) can be renamed, and the name above each group can be switched off.
- A switched-off heading is only hidden visually. It stays in the page for screen readers and search engines.
- No database change; existing sites look the same until you edit a text.

## 0.6.0

- Photo credits: an optional photographer name and link for the main photo (*Profile*), each gallery photo (*Photos*), blog cover images (*Blog*) and pictures inside a post. Shown as "Photo: Name" on the picture, with the name linked when a link is given. Nothing is shown when the fields are empty.
- In the blog editor, the *Image...* button asks for an optional credit after the upload. In Markdown: `![Caption](/uploads/x.jpg "Name | https://link")`.
- Adds columns to `media` and `posts`. Existing content is not touched.

## 0.5.1

- Fix: the design cards on *Admin -> Appearance* were drawn broken (torn borders, stray white blocks). Styling only.

## 0.5.0

- Contact form replaces the visible email address. Visitors write name, email and message; the message is emailed to the contact address from the profile and saved in **Admin -> Messages** (new inbox, with unread markers and a notice on the dashboard).
- **Admin -> Email** (admins): SMTP server, port, encryption, login and sender, plus a "send a test email" button. Without an SMTP server the server's own mail function is used.
- Spam protection without a captcha: hidden trap field, signed form time (too fast or too old is refused), at most 5 messages per hour per visitor and 40 per hour in total, and a limit on links. The visitor's IP address is stored only as a salted hash.
- A message is saved even if the email fails, and the inbox shows which ones were not emailed and why.
- Adds the `messages` table. Existing content is not touched.

## 0.4.0

- User levels: **Admin** (everything), **Editor** (content, blog, redirects, 404 page; publishes posts), **Author** (writes own posts, which go live after approval). New *Admin -> Users* page to add, change and remove people.
- Blog posts now have a status "Waiting for review". Authors see only their own posts and can change them until they are published.
- A user can be made to choose their own password at the first login.
- Safety rules: at least one admin must always remain; you can not delete yourself or lower your own level.
- Adds columns to `users` (`role`, `name`, `must_change`) and `posts` (`author_id`). Existing users stay admins.

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
