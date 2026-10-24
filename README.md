# Rider Page

A one-page portfolio with a blog for athletes, writers and other creators. You upload it to your
own web hosting, answer a few questions in a browser, and get a website with:

- your photo, introduction and social accounts (Instagram, TikTok, YouTube, Strava and more)
- results, partners and sponsors with logos and links
- a YouTube video and a photo gallery
- a blog with pictures, links and YouTube videos
- an FAQ and a contact section
- a sticky menu that jumps to each section, in an order you choose
- 10 designs with editable colours

It needs no command line, no Composer and no Node. Updates are installed from the admin area.

## What you need

- A web hosting with **PHP 8.1 or newer** (with the `gd`, `mbstring` and `zip` extensions, which almost every host has)
- A **database**: SQLite works without any setup. MySQL or MariaDB also work (you enter the details in the installer).
- Apache with `.htaccess` support (standard on shared hosting). For nginx see below.

## Install

1. Download `rider-page-X.Y.Z.zip` from the [latest release](../../releases/latest) and unzip it.
2. Upload everything to your hosting (the main folder of your domain, or a sub-folder) by FTP or the file manager.
3. Open your domain in a browser. The setup page appears.
4. Fill in your name, admin email and password, pick a design, and press **Install**.
5. You land in the admin area. Fill in your profile, add partners, write a post.

The setup page disappears by itself once the site is installed.

### nginx

nginx ignores `.htaccess`, so add this to the server block (adjust the PHP socket):

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ ^/(app|storage)/ { deny all; }
location ~ ^/uploads/.*\.php$ { deny all; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php-fpm.sock; }
```

## The admin area

Open `/admin` on your site.

**User levels:** *Admin* can do everything. *Editor* manages content, the blog, redirects and the 404 page and can publish posts, but cannot change the design, run updates, use maintenance mode or manage users. *Author* writes their own blog posts; a post goes live after an editor or admin approves it.

| Page | What it does |
| --- | --- |
| Profile | Name, introduction, photo, badge, contact email, YouTube video, search description, language |
| Social accounts, Results, Partners, Photos, FAQ | Lists you can add to, edit, reorder and delete |
| Blog | Posts with a cover image, optional photo credits, pictures, links and YouTube. Drafts and future dates are supported |
| Sections | Order of the sections, switch sections on or off, rename headings and partner levels. Empty sections hide themselves |
| Appearance | 10 presets, accent colour, background and text colour, with a contrast warning |
| 404 page, Redirects | Edit the page for unknown addresses; redirect old addresses to new ones; see which addresses were not found |
| Maintenance | Switch the page offline for visitors with your own message. You still see the real site while logged in |
| Updates | Check for and install new versions |
| Messages, Email | Inbox for the contact form; SMTP settings so messages reach you reliably |
| Pages, Legal pages | Own pages (about, legal notice, privacy). Guided set-up of the legal pages for your country, as drafts you review and publish |
| Users | Add people and give them a level: Admin, Editor or Author (admins only) |
| Account | Change your name, email and password |

## Updating

**From the admin area (recommended):** open *Updates*, press *Check for updates*, then *Back up and update*.

What happens:

1. The release is downloaded from GitHub and checked against its published checksum.
2. A backup of the code and the database is saved in `storage/backups`.
3. The code folders (`app`, `themes`, `assets`) are replaced. Your content, images and settings are not touched.
4. Database changes run. The start page is tested.
5. If anything fails, the previous version is restored automatically (and, with SQLite, the previous database too).

Each release says in its notes whether it can affect customisations (`breaking`) and which PHP version it needs.
New features are off or hidden until you set them up.

**By hand:** download the zip, upload the folders `app`, `themes`, `assets` and the files `index.php` and `manifest.json`
over the existing ones. Database changes run on the next page load. Never overwrite `storage` or `uploads`.

**If an update leaves the site broken** (for example a server restart in the middle of it): the last three backups are in
`storage/backups`. Unzip the newest `code-*.zip` over your site and the site is back at the previous version.

## Backups

The updater makes a backup before every update. For regular backups, copy the `storage` and `uploads` folders
(SQLite keeps the whole database inside `storage`; with MySQL also export the database).

## Privacy and security

- Fonts are hosted by your own site. No request goes to Google when someone visits.
- YouTube videos load only when a visitor presses play, and then from `youtube-nocookie.com`.
- The public pages set no cookies.
- The contact form stores each visitor's IP address only as a salted hash (for the rate limit). Messages contain personal data: mention the form in your privacy policy.
- Passwords are hashed, logins are rate limited, forms are protected against cross-site requests,
  and uploaded images are re-encoded so only pixels are kept (SVG is not accepted).
- `storage/` holds your database and backups. It is blocked from the web by `.htaccess` (Apache) or the nginx rule above.
  Check that `https://your-domain/storage/config.php` shows an error and not a download.

## For developers

```bash
php tests/run.php        # installs a copy in a temp folder and tests pages, images, updates and rollback
php -S localhost:8000    # local preview (storage/ and uploads/ must be writable)
```

### Releasing a version

1. Raise `version` in `manifest.json` and add notes to `CHANGELOG.md`. Set `breaking` and `warnings` if needed.
2. Commit, then tag: `git tag v0.2.0 && git push --tags`.
3. The *Release* workflow tests the code, builds `rider-page-0.2.0.zip` with its `.sha256` checksum and publishes the release.
   Sites find it through *Admin → Updates*.

### Rules that keep updates safe

- **Migrations only add.** New tables and columns get defaults. Never rename or drop something that an older version reads.
  Files live in `app/migrations/NNNN_name.php` and run once, in order.
- **Settings have defaults in code,** so an old site without the new setting keeps working.
- **New features start switched off or hidden** until the owner has content for them.
- **Customisations live outside the replaced folders** (`storage/`, `uploads/`, the database).
- **Code that runs right after an update must not need new columns.** The update's last step (`/admin/update/finish`) runs with the new code before the database changes are applied, so login and the update page must work with the old schema (see `Auth::user()`).
- **Breaking changes need a major version,** `"breaking": true` in the manifest and a clear note.

### Layout

```
index.php        front controller
app/             PHP code, migrations, language files, admin views   (replaced on update)
themes/          public templates                                    (replaced on update)
assets/          CSS, JS, self-hosted fonts                          (replaced on update)
storage/         config, database, backups, logs                     (never replaced)
uploads/         images                                              (never replaced)
docs/mockups/    design explorations
tests/           smoke tests
```

## Known limits in 0.1

- Designs have light or dark palettes by preset; there is no automatic switch to the visitor's dark mode yet.
- Fonts are the Latin subset (covers English, German, Scandinavian, French, Spanish and similar).
- Logos must be PNG, JPG or WebP (no SVG, for security).
- The contact form has no captcha. It uses a hidden trap field, timing and rate limits, which stops most bots.
- The admin area is in English; the public page texts are English or German.

## License

MIT. See `LICENSE`. Fonts are under the SIL Open Font License.
