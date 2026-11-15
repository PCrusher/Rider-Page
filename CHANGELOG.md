# Changelog

Versions follow semantic versioning. Patch and minor releases are safe to install on any
site. A major release may contain breaking changes and says so in `manifest.json`.

## 0.18.0

- **Originals are kept.** Every new upload is saved twice: a smaller copy for your page, and the untouched original in `storage/originals`, where visitors can not reach it. The new *Images* page (*Content* menu) lists the originals with a small preview, the original file name and size, and a *Download* button for the full-quality file. Individual originals or all of them can be deleted; the pictures on your page stay.
- **Size and quality are your choice** (*Images -> Settings*, admins): longest side of the page pictures 1200, 1600, 1800 (default) or 2400 px, JPEG quality 60 to 95 (default 84), and a switch to stop keeping originals when your hosting has little space. Previews of 800 px are always made. Changes apply to new uploads.
- Original files can contain the place where a photo was taken. They are never public, and the pictures on your page still have that data removed.
- Pictures uploaded before this version have no original. Backups made by the updater do not include originals; copy `storage/originals` yourself if you want them in your own backup.
- No database change.

## 0.17.1

Security hardening after a review of login, sessions, forms, uploads and updates.

- **Emails no longer take their links from the Host header.** The password reset link, the QR login link and the address in security emails now use the *Site address* from *Profile* or, if that is empty, the address your admin area was last opened on. Before, someone who sent a forged `Host:` header with a "forgot password" request for your email address could make the link in your email point to their own site. A malformed Host header is ignored everywhere.
- **Login attempts are also limited per account** (20 wrong passwords in 15 minutes, from any addresses), not only per address. Before, a guessing attack spread over many addresses was not slowed down.
- **An unknown email address takes as long to answer as a wrong password,** so the login page no longer shows which addresses have an account.
- **New response headers:** `Permissions-Policy` (camera, microphone, location and more are off) and `Cross-Origin-Opener-Policy`. Sessions now use strict mode and cookies only.
- **The template folder is blocked from direct access** (`themes/views/.htaccess`).
- No database change.

## 0.17.0

- **Visits by hour** (*Statistics*): a chart of the last 24 hours and a chart of the busiest hours of the day over the chosen period (7, 30 or 90 days).
- **Country and city of visitors.** Switch it on under *Statistics -> Settings -> Location*. Your server looks the visit up in a free GeoLite2 database that is stored on the server itself (`storage/geo`), so nothing is sent to anyone. The IP address is still never stored, only the country and city. Choose *City* (about 50 MB, country and city) or *Country* (about 3 MB). The *Statistics* page then lists the top countries and cities.
- **The database is downloaded and kept up to date from the admin area** (from the GeoLite.mmdb mirror on GitHub): *Download / update now*, or automatically once a week while you use the admin area. A new file is checked before it replaces the old one. It is not part of the updates and is never touched by them.
- CSV downloads for hours, countries and cities. The privacy templates mention the location lookup while it is on (update your pages under *Legal pages*).
- Location is an estimate and is often a few hundred kilometres off. Earlier visits stay without a location.
- Adds the columns `cc`, `country` and `city` to `hits`.

## 0.16.0

- **Update notice in the menu.** When an admin opens the admin area, the page checks GitHub for a new version in the background (at most once every 6 hours, never slowing the page down). If a newer version exists, *Updates* in the menu gets an amber *New* badge. A failed check is ignored quietly.
- **Select several messages.** In *Messages* every row has a checkbox, with one to select all. *Delete* removes the selected messages; in the Spam tab, *Not spam* moves the selected ones to the inbox and emails them to you.
- No database change.

## 0.15.0

- **Spam tab in Messages.** Every message from the contact form gets spam points: sales wording (SEO, traffic, "your website" ...), your own domain named in the text, links, throwaway email addresses, a link or address in the name, the same text sent by another address, and a form that was not completed by a browser. At 50 points or more the message is **kept in a Spam tab and not emailed to you**. There you can press *Not spam* (it moves to the inbox and is emailed now), delete one, or *Delete all spam*. Spam is deleted after 30 days. The sender still sees the normal thank-you, so bots do not adapt.
- **Invisible proof of work.** The page solves a small puzzle in the background while the visitor types (about a second, nothing to click, no third party, no cookie). Bots that post the form directly do not solve it. A message without it is not refused, it only gets points, so visitors without scripts or on plain http are not lost.
- Messages from the spam filter do not count towards the 40-per-hour limit, so a flood of spam can not block real visitors.
- Adds the columns `spam`, `spam_score` and `spam_reasons` to `messages`.

## 0.14.0

- **New layout "Paddock"** with two presets, *Paddock* (copper accent) and *Paddock Ice* (ice blue). A dark, photographic page: the main photo fills the top of the page, the name is set very large at the bottom right, the tagline follows in the accent colour, and the discipline line in small mono type. Sections have thin divider lines and large uppercase headings, results show as big numbers with an accent line, buttons are pills with an arrow, and the menu has a light button on a dark bar. A fine grain texture sits behind everything. On phones the text is left-aligned.
- **Small line under the name** (*Profile*): optional, for example your disciplines ("Enduro · E-MTB · Downhill"). It is shown in small type in every layout, and is empty until you fill it in.
- 12 designs on 5 layouts now. No database change.

## 0.13.0

- **Two-factor login.** Under *Account* you can set up an authenticator app (Google Authenticator, Microsoft Authenticator, Aegis, 1Password and others): scan the QR code, confirm with a code, and save ten one-time recovery codes. After that, the login asks for a code after the password. A code works once, and wrong codes are limited. Switching it off or making new recovery codes needs the password and a code. The secret is stored encrypted with a key that is not in the database.
- **Require it for admins** (*Users -> Two-factor login*): admins without two-factor login are taken to the Account page until it is set up. Admins can switch off the two-factor login of another user who lost their phone and codes.
- **QR login with two-factor on:** showing a QR code then also asks for a current app code.
- **Forgot password.** The login page links to a form. It sends a link by email that works once for 60 minutes. The answer is the same for known and unknown addresses, and requests are limited. After the change all devices are logged out; with two-factor on, the code is still needed.
- **Emergency recovery without email.** Create the file `storage/RESET_ADMIN` on the server (for example by FTP) with a secret phrase of at least 10 characters. For 15 minutes, `/admin/recover` accepts that phrase, an account email and a new password. The file is deleted afterwards and two-factor login of that account is switched off.
- **Security emails.** You get an email when someone logs in with a QR code, from a new device (can be switched off under *Account*), and when your password or two-factor login changes or a recovery code is used. Needs a working email set-up under *Admin -> Email*.
- Adds the columns `totp_secret`, `totp_enabled`, `totp_step`, `notify` to `users` and the tables `recovery_codes`, `login_devices` and `password_resets`. Existing logins stay valid.

## 0.12.0

- **Statistics** (*Admin -> Statistics*, for admins and editors): page views and visitors per day as a chart for 7, 30 or 90 days, top pages and posts, where visitors came from, devices and browsers, and clicks on partner links, social accounts and the video. CSV download for each table.
- **Privacy by design.** No cookie is set and no IP address is stored. A visitor is counted once per day through a code that changes every day, so nobody can be followed from one day to the next. Bots, logged-in admins, prefetching and visitors who send "Do Not Track" or "Global Privacy Control" are not counted. Detailed data is kept for 30 days, 90 days or one year (you choose) and then only the daily totals remain. All of it can be deleted with one button.
- **New installs have statistics on; existing sites start with them off**, so nothing is collected without you knowing. Switch them on under *Statistics -> Settings*, then update the privacy policy under *Legal pages* ("Replace pages I created from these templates"): the templates now describe the statistics when they are on.
- **Cookie notice** (*Admin -> Cookie notice*, off by default): a small bar that informs visitors about cookies and data and links to the privacy policy. Your own text and button are possible. It is explained clearly that this site needs no consent banner as it is built, and that the notice would not be enough if tracking tools were added.
- The dashboard shows the last 7 days. The privacy templates no longer say that no cookie banner is shown.
- Adds the tables `hits` and `hit_days`.

## 0.11.0

- **Version history on the Updates page.** Before you update, the page shows what is new in the version that is available (taken from this changelog). Below it, a history of every version with details of what changed, newest first, with your version marked. The history comes with every update, so it is always current.
- **Side menu with more contrast.** The menu on the left now has its own dark background, bold group headings with a divider between the groups, a clearer hover state and a strongly highlighted current page, in light and dark mode.
- **Menu for phones reworked.** On a phone the pages are no longer squeezed into wrapped lines. A bar at the top shows the name of the current page and a *Menu* button that opens the full list in large, easy-to-tap rows, with *View site*, *Log out* and your version at the bottom. Escape closes it. Without JavaScript the list is simply always visible.
- No database change.

## 0.10.0

- **QR login works the other way round.** On the *Account* page of a computer where you are logged in, you show a QR code (after typing your password). You scan it with your phone's camera, tap one button, and the phone is logged in without a password. The code is valid for 20 seconds, works once, and is shown again by hand. It replaces the login-page QR code with the 4-digit number from 0.9.0, which started on the computer you wanted to log in on.
- Safety: the code is only created for a logged-in user who switched QR login on, the password is asked every time, at most 5 codes per 10 minutes, the phone must tap a button (so link previews can not use the code), and every login shows up in the list of devices where it can be ended.
- The computer is told when the phone has opened the code and when the login happened.
- Adds the table `qr_tokens`. The 0.9.0 table `qr_logins` is no longer used and stays empty.

## 0.9.0

- **Login with a QR code**: the login page can show a QR code. You scan it with a phone that is already logged in, type the 4-digit number shown on the computer's screen, and the computer is logged in without a password. The code is valid for 20 seconds and is refreshed by hand. It is off by default and each user switches it on under *Account* (after entering their password).
- Safety: single-use codes, a separate secret that stays on the computer's page, the number check (so a QR code shown by someone else can not be used to trick you), three tries, and the phone shows which device and IP address is asking.
- **Where you are logged in** (*Account*): every logged-in browser with device, IP address, login method, start and last activity. End any of them, or all other devices. A new password logs out the other devices; removing a user ends their logins.
- Adds the tables `login_sessions` and `qr_logins` and the column `users.qr_enabled`. Existing logins are adopted and stay valid.

## 0.8.0

- **Pages** (*Admin -> Pages*): your own pages with a title, an address, simple text formatting and draft or published status. Published pages can be linked in the footer of every page.
- **Legal pages** (*Admin -> Legal pages*): choose where you are based (Germany/Austria/Switzerland, another EU or EEA country, United Kingdom, United States, elsewhere). The page explains which legal pages are required or expected there and why, then creates draft pages from templates that are filled with your details: legal notice (Impressum), privacy policy and a sponsorship and advertising page. Texts in English and, where it applies, German.
- The privacy policy follows what the site does (contact form, YouTube, sponsors) and your own notes on other services. Pages you wrote yourself are never overwritten.
- Legal pages are noindex and stay out of the sitemap. The dashboard reminds admins until a legal page is published.
- The texts are templates, not legal advice. Adds a `pages` table.

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
