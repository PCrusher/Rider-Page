<?php
declare(strict_types=1);

/**
 * Smoke tests. Run with:  php tests/run.php
 * Copies the project to a temp folder, installs it there with SQLite, renders the pages,
 * and then tests an update (and a failing update that must roll back).
 */

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$src = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/rp-test-' . bin2hex(random_bytes(4));

function rcopy(string $from, string $to): void
{
    if (is_dir($from)) {
        @mkdir($to, 0755, true);
        foreach (scandir($from) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                rcopy("$from/$f", "$to/$f");
            }
        }
    } else {
        copy($from, $to);
    }
}
function rrm(string $d): void
{
    if (!is_dir($d)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($d);
}

$pass = 0;
$fail = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ok   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label" . ($detail !== '' ? "  ($detail)" : '') . "\n";
        if (getenv('GITHUB_ACTIONS')) {
            // Shown as an annotation on the workflow run, so failures are visible without the full log.
            echo '::error title=Test failed::' . str_replace(["\r", "\n"], ' ', $label . ($detail !== '' ? ' (' . $detail . ')' : '')) . "\n";
        }
    }
}

foreach (['app', 'themes', 'assets', 'storage', 'uploads'] as $d) {
    rcopy("$src/$d", "$tmp/$d");
}
copy("$src/index.php", "$tmp/index.php");
copy("$src/manifest.json", "$tmp/manifest.json");
copy("$src/CHANGELOG.md", "$tmp/CHANGELOG.md");

define('RP_ROOT', $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require $tmp . '/app/bootstrap.php';

try {
    echo "Presets\n";
    foreach (Presets::all() as $id => $p) {
        check(in_array($p['layout'], Presets::LAYOUTS, true), "$id has a known layout");
        check(Presets::contrast($p['fg'], $p['bg']) >= 4.5, "$id text contrast", (string)round(Presets::contrast($p['fg'], $p['bg']), 2));
        check(Presets::contrast($p['accent'], $p['bg']) >= 3, "$id accent contrast", (string)round(Presets::contrast($p['accent'], $p['bg']), 2));
        check(Presets::contrast($p['accent'], Presets::inkFor($p['accent'])) >= 4.5, "$id button text contrast");
    }
    check(count(Presets::all()) === 10, 'ten presets');

    echo "Markdown\n";
    $html = Markdown::render("Hello <script>alert(1)</script> **bold** [x](javascript:alert(1)) [ok](https://example.com)\n\nhttps://youtu.be/dQw4w9WgXcQ\n\n![alt](/uploads/a.jpg)");
    check(!str_contains($html, '<script>'), 'raw HTML is escaped');
    check(!str_contains($html, 'javascript:'), 'javascript: links are dropped');
    check(str_contains($html, '<strong>bold</strong>'), 'bold works');
    check(str_contains($html, 'rel="noopener noreferrer"'), 'external links get rel');
    check(str_contains($html, 'data-yt="dQw4w9WgXcQ"'), 'YouTube link becomes a click-to-load player');
    check(str_contains($html, '<figure>'), 'image becomes a figure');
    check(youtube_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5') === 'dQw4w9WgXcQ', 'youtube_id watch URL');
    check(youtube_id('not a video') === null, 'youtube_id rejects text');

    echo "Install\n";
    $m = new ReflectionMethod(Installer::class, 'install');
    $uid = $m->invoke(null, [
        'site_name' => 'Test Rider', 'lang' => 'en', 'preset' => 'night', 'timezone' => 'UTC', 'db_driver' => 'sqlite',
        'mysql_host' => '', 'mysql_port' => '', 'mysql_name' => '', 'mysql_user' => '', 'mysql_pass' => '',
        'admin_email' => 'admin@example.com', 'example' => '1',
    ], 'a-long-test-password');
    check($uid > 0, 'installer creates the admin user');
    check(is_file($tmp . '/storage/config.php'), 'config.php written');
    check(Migrator::run() === [], 'migrations are idempotent');
    check(Auth::attempt('admin@example.com', 'wrong') !== true, 'wrong password is rejected');

    echo "Public pages\n";
    $r = Site::response('/');
    check($r['status'] === 200, 'home 200');
    check(str_contains($r['body'], 'Test Rider'), 'home shows the name');
    check(str_contains($r['body'], 'data-design="night"'), 'home uses the chosen layout');
    check(substr_count($r['body'], '<h1') === 1, 'exactly one h1');
    check(str_contains($r['body'], 'id="sponsors"') && str_contains($r['body'], 'id="faq"'), 'sections rendered');
    check(preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $r['body'], $mm) === 1 && json_decode($mm[1], true) !== null, 'JSON-LD is valid JSON');
    check(str_contains($r['body'], 'FAQPage'), 'FAQ structured data present');
    check(!str_contains($r['body'], 'style="'), 'no inline style attributes (CSP)');
    $r = Site::response('/blog');
    check($r['status'] === 200 && str_contains($r['body'], 'Welcome to my new page'), 'blog index lists the example post');
    $r = Site::response('/blog/welcome');
    check($r['status'] === 200 && str_contains($r['body'], '<strong>bold</strong>'), 'post renders markdown');
    check(Site::response('/blog/missing')['status'] === 404, 'unknown post is 404');
    check(Site::response('/nope')['status'] === 404, 'unknown page is 404');
    check(str_contains(Site::response('/sitemap.xml')['body'], '/blog/welcome'), 'sitemap lists the post');
    check(str_contains(Site::response('/robots.txt')['body'], 'Disallow: /admin'), 'robots.txt blocks admin');

    echo "Sections\n";
    Settings::set('sections_off', 'sponsors');
    check(!str_contains(Site::response('/')['body'], 'id="sponsors"'), 'switched-off section is not rendered');
    Settings::set('sections_off', '');
    Settings::set('sections_order', 'faq,contact,blog,media,sponsors,results');
    $body = Site::response('/')['body'];
    check(strpos($body, 'id="faq"') < strpos($body, 'id="sponsors"'), 'order setting is respected');
    Db::run('DELETE FROM faqs');
    check(!str_contains(Site::response('/')['body'], 'id="faq"'), 'empty section hides itself');
    Settings::set('color_accent', '#FF0000');
    check(str_contains(Presets::css(), '--s-accent:#FF0000'), 'accent override applied');
    Settings::set('color_accent', '');

    echo "404 and redirects\n";
    $r = Site::response('/does-not-exist');
    check($r['status'] === 404, 'unknown address is 404');
    check((int)Db::val("SELECT COUNT(*) FROM not_found WHERE path = '/does-not-exist'") === 1, '404 address is logged');
    Site::response('/does-not-exist');
    check((int)Db::val("SELECT hits FROM not_found WHERE path = '/does-not-exist'") === 2, 'repeated 404 is counted');
    Site::response('/__preview-404');
    check((int)Db::val("SELECT COUNT(*) FROM not_found WHERE path = '/__preview-404'") === 0, 'preview is not logged');
    Settings::set('e404_title', 'Lost?');
    $body = Site::response('/zzz')['body'];
    check(str_contains($body, 'Lost?'), 'custom 404 heading');
    check(str_contains($body, '/blog/welcome'), '404 page lists the latest posts');
    Settings::set('e404_posts', '0');
    check(!str_contains(Site::response('/zzz')['body'], '/blog/welcome'), '404 posts list can be switched off');
    Settings::set('e404_title', '');
    Settings::set('e404_posts', '1');

    [$d, $e] = Redirects::prepare('/old-post/', '/blog/welcome', 301);
    check($e === null && $d['from_path'] === '/old-post', 'redirect is accepted and normalised', (string)$e);
    Db::insert('redirects', $d + ['hits' => 0, 'created_at' => now()]);
    $r = Site::response('/old-post');
    check($r['status'] === 301 && in_array('Location: /blog/welcome', $r['headers'], true), 'redirect answers 301 with Location');
    check((int)Db::val("SELECT hits FROM redirects WHERE from_path = '/old-post'") === 1, 'redirect use is counted');
    [$d2] = Redirects::prepare('/blog', '/elsewhere', 301);
    Db::insert('redirects', $d2 + ['hits' => 0, 'created_at' => now()]);
    check(Site::response('/blog')['status'] === 200, 'a real page is never shadowed by a redirect');
    Db::run("DELETE FROM redirects WHERE from_path = '/blog'");
    check(Redirects::prepare('/admin/x', '/blog', 301)[1] !== null, 'system addresses are rejected');
    check(Redirects::prepare('/a', '/a', 301)[1] !== null, 'redirect to itself is rejected');
    check(Redirects::prepare('/blog/welcome', '/old-post', 301)[1] !== null, 'redirect loop is rejected');
    check(Redirects::prepare('/old-post', '/y', 301)[1] !== null, 'duplicate is rejected');
    check(Redirects::prepare('/x', 'javascript:alert(1)', 301)[1] !== null, 'unsafe target is rejected');
    [$d3] = Redirects::prepare('https://example.com/Old/', 'https://other.test/new', 302);
    check($d3['from_path'] === '/Old' && $d3['code'] === 302, 'full URL as old address is reduced to its path');

    echo "Maintenance\n";
    Settings::set('maintenance', '1');
    Settings::set('maintenance_title', 'Back soon');
    $r = Site::response('/');
    check($r['status'] === 503, 'maintenance mode answers 503');
    check(str_contains($r['body'], 'Back soon') && !str_contains($r['body'], 'id="sponsors"'), 'maintenance page replaces the site');
    check(in_array('Retry-After: 3600', $r['headers'], true), 'Retry-After header is sent');
    check(str_contains($r['body'], 'noindex'), 'maintenance page is noindex');
    check(Site::response('/blog/welcome')['status'] === 503, 'blog is in maintenance too');
    check(Site::response('/robots.txt')['status'] === 200, 'robots.txt stays available');
    Settings::set('maintenance', '');
    check(Site::response('/')['status'] === 200, 'site is back when maintenance is off');

    echo "Users and roles\n";
    check(Users::find($uid)['role'] === 'admin', 'installer admin has the admin level');
    check(Roles::can('admin', 'update') && Roles::can('admin', 'users') && Roles::can('admin', 'appearance'), 'admin can open everything');
    check(!Roles::can('editor', 'update') && !Roles::can('editor', 'users') && !Roles::can('editor', 'maintenance') && !Roles::can('editor', 'appearance'), 'editor can not open system pages');
    check(Roles::can('editor', 'posts') && Roles::can('editor', 'profile') && Roles::can('editor', 'redirects') && Roles::can('editor', 'errorpage'), 'editor can edit content');
    check(Roles::can('author', 'posts') && Roles::can('author', 'account') && Roles::can('author', 'upload'), 'author can write posts');
    check(!Roles::can('author', 'profile') && !Roles::can('author', 'media') && !Roles::can('author', 'redirects') && !Roles::can('author', 'sections'), 'author can not edit the rest');
    check(!Roles::can('editor', 'some-future-area'), 'areas not listed are admin only');
    check(!Roles::can('guest', '') && !Roles::valid('guest'), 'unknown level has no access');
    check(array_keys(Roles::postStatuses('author')) === ['draft', 'pending'], 'authors can not publish');
    check(array_key_exists('published', Roles::postStatuses('editor')), 'editors can publish');

    $ok = ['email' => 'new@example.com', 'name' => '', 'role' => 'editor', 'password' => 'a-long-test-password'];
    check(Users::validate($ok, 0) === [], 'valid new user passes');
    check(Users::validate(['email' => 'admin@example.com'] + $ok, 0) !== [], 'duplicate email is refused');
    check(Users::validate(['password' => 'short'] + $ok, 0) !== [], 'short password is refused');
    check(Users::validate(['password' => ''] + $ok, 0) !== [], 'a new user needs a first password');
    check(Users::validate(['role' => 'boss'] + $ok, 0) !== [], 'unknown level is refused');
    check(Users::validate(['email' => 'admin@example.com', 'role' => 'author', 'password' => ''] + $ok, $uid) !== [], 'the last admin can not be demoted');

    $eid = Users::save(['email' => 'Editor@Example.com', 'name' => 'Eva', 'role' => 'editor', 'must_change' => true] + $ok, 0);
    $aid = Users::save(['email' => 'author@example.com', 'name' => '', 'role' => 'author', 'must_change' => false] + $ok, 0);
    $ed = Users::find($eid);
    check($ed['email'] === 'editor@example.com' && $ed['role'] === 'editor' && (int)$ed['must_change'] === 1, 'user is saved with lower-case email, level and flag');
    check(password_verify('a-long-test-password', (string)$ed['password_hash']) && $ed['password_hash'] !== 'a-long-test-password', 'password is stored hashed');
    Users::save(['email' => 'editor@example.com', 'name' => 'Eva K', 'role' => 'editor', 'password' => '', 'must_change' => false], $eid);
    $ed = Users::find($eid);
    check($ed['name'] === 'Eva K' && (int)$ed['must_change'] === 0 && password_verify('a-long-test-password', (string)$ed['password_hash']), 'empty password field keeps the old password');
    check(Users::label($ed) === 'Eva K' && Users::label(Users::find($aid)) === 'author@example.com', 'label uses name or email');

    $a2 = Users::save(['email' => 'admin2@example.com', 'role' => 'admin', 'must_change' => false] + $ok, 0);
    check(Users::validate(['email' => 'admin@example.com', 'role' => 'editor', 'password' => ''] + $ok, $uid) === [], 'an admin can be demoted when another admin exists');
    check(Users::delete($uid, $uid) !== null, 'you can not delete yourself');
    check(Users::delete($a2, $uid) === null && Users::find($a2) === null, 'another admin can be removed');
    check(Users::delete($uid, $eid) !== null, 'the last admin can not be removed');

    $draft = ['id' => 1, 'author_id' => $aid, 'status' => 'draft'];
    $live = ['id' => 2, 'author_id' => $aid, 'status' => 'published'];
    $me = ['id' => $aid, 'role' => 'author'];
    check(Roles::canEditPost($me, $draft) && Roles::canEditPost($me, ['status' => 'pending'] + $draft), 'author edits own draft and pending posts');
    check(!Roles::canEditPost($me, $live), 'author can not edit a published post');
    check(!Roles::canEditPost(['id' => $aid + 99, 'role' => 'author'], $draft), 'author can not edit someone else\'s post');
    check(Roles::canEditPost(['id' => $eid, 'role' => 'editor'], $live) && Roles::canEditPost(['id' => $uid, 'role' => 'admin'], $draft), 'staff edit any post');

    Db::insert('posts', ['slug' => 'by-author', 'title' => 'By author', 'summary' => '', 'cover' => '', 'body' => 'x', 'status' => 'pending',
        'published_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'author_id' => $aid]);
    check(Site::response('/blog/by-author')['status'] === 404, 'a post waiting for review is not public');
    check(Users::delete($aid, $uid) === null && (int)Db::val("SELECT author_id FROM posts WHERE slug = 'by-author'") === $uid, 'removing a user hands their posts to you');
    Db::run("DELETE FROM posts WHERE slug = 'by-author'");
    Db::run("DELETE FROM not_found");

    echo "Contact form\n";
    $sent = [];
    Mailer::$transport = static function (array $m) use (&$sent): array {
        $sent[] = $m;
        return [true, ''];
    };
    $tok = Contact::token(time() - 10);
    $form = ['name' => 'Pat Visitor', 'email' => 'pat@example.org', 'message' => 'Hello, I would like to talk about a partnership.', 'website' => '', 't' => $tok[0], 's' => $tok[1]];
    $r = Contact::submit($form, '203.0.113.5');
    check($r['ok'] && count($sent) === 1, 'valid message is accepted and emailed');
    check($sent[0]['to'] === 'admin@example.com' && $sent[0]['reply_to'] === 'pat@example.org' && str_contains($sent[0]['subject'], 'Pat Visitor'), 'mail goes to the owner with the visitor as reply-to');
    check((int)Db::val('SELECT COUNT(*) FROM messages') === 1 && Db::val('SELECT mail_status FROM messages') === 'sent', 'message is saved with status sent');
    check(Db::val('SELECT ip_hash FROM messages') !== '203.0.113.5' && strlen((string)Db::val('SELECT ip_hash FROM messages')) === 32, 'IP address is stored only as a hash');
    $home = Site::response('/')['body'];
    check(!str_contains($home, 'admin@example.com'), 'the owner email address is not shown on the page');
    check(str_contains($home, 'action="/contact"') && str_contains($home, 'name="message"') && str_contains($home, 'name="website"'), 'contact form is on the page');

    $before = count($sent);
    check(Contact::submit(['website' => 'http://spam.test'] + $form, '203.0.113.6')['ok'] && count($sent) === $before && (int)Db::val('SELECT COUNT(*) FROM messages') === 1, 'filled honeypot is dropped silently');
    $fast = Contact::token(time());
    check(Contact::submit(['t' => $fast[0], 's' => $fast[1]] + $form, '203.0.113.7')['error'] === 'expired', 'a form sent too fast is refused');
    check(Contact::submit(['s' => 'deadbeef'] + $form, '203.0.113.7')['error'] === 'expired', 'a forged token is refused');
    $old = Contact::token(time() - 8000);
    check(Contact::submit(['t' => $old[0], 's' => $old[1]] + $form, '203.0.113.7')['error'] === 'expired', 'an old form is refused');
    check(Contact::submit(['email' => 'not-an-email'] + $form, '203.0.113.7')['error'] === 'invalid', 'invalid email is refused');
    check(Contact::submit(['email' => "pat@example.org\r\nBcc: x@y.z"] + $form, '203.0.113.7')['error'] === 'invalid', 'email with a line break is refused');
    check(Contact::submit(['message' => 'short'] + $form, '203.0.113.7')['error'] === 'invalid', 'too short message is refused');
    check(Contact::submit(['name' => ''] + $form, '203.0.113.7')['error'] === 'invalid', 'missing name is refused');
    check(Contact::submit(['message' => 'see http://a.test http://b.test http://c.test now'] + $form, '203.0.113.7')['error'] === 'spam', 'link-heavy message is refused');
    Contact::submit(['name' => "Evil\r\nBcc: x@y.z"] + $form, '203.0.113.8');
    $last = end($sent);
    check(!str_contains($last['subject'], "\n") && !str_contains($last['subject'], "\r"), 'line breaks in the name can not inject mail headers');

    $ok = 0;
    for ($i = 0; $i < 5; $i++) {
        $ok += Contact::submit($form, '198.51.100.9')['ok'] ? 1 : 0;
    }
    check($ok === 5 && Contact::submit($form, '198.51.100.9')['error'] === 'rate', 'a sixth message in an hour from one visitor is refused');

    Mailer::$transport = static fn(array $m): array => [false, 'smtp is down'];
    $r = Contact::submit($form, '198.51.100.10');
    check($r['ok'] && Db::val("SELECT mail_status FROM messages WHERE ip_hash = '" . substr(hash('sha256', '198.51.100.10' . rp_config()['secret']), 0, 32) . "'") === 'failed', 'a failed email still saves the message and marks it');
    Mailer::$transport = null;
    Db::run('DELETE FROM messages');

    echo "Changelog\n";
    $hist = Changelog::entries();
    check(count($hist) >= 10 && $hist[0]['version'] === Updater::version(), 'the version history is read from CHANGELOG.md, newest first', $hist ? $hist[0]['version'] : 'empty');
    check(str_contains($hist[0]['html'], '<li>') && !str_contains($hist[0]['html'], '<script'), 'an entry is rendered as a list');
    check(str_contains(end($hist)['html'], 'First release'), 'the oldest entry is the first release');

    echo "Photo credits\n";
    check(credit_html('') === '', 'no name and no link gives no credit');
    check(str_contains(credit_html('Jane <b>Doe</b>'), 'Jane &lt;b&gt;Doe&lt;/b&gt;') && !str_contains(credit_html('Jane <b>Doe</b>'), '<a '), 'credit name is escaped and not linked without a link');
    check(str_contains(credit_html('Jane', 'https://example.com/jane'), 'href="https://example.com/jane"'), 'credit links to the photographer');
    check(!str_contains(credit_html('Jane', 'javascript:alert(1)'), 'href'), 'unsafe credit link is dropped');
    check(str_contains(credit_html('', 'https://www.example.com/x'), '>example.com</a>'), 'link without a name uses the domain');
    $md = Markdown::render('![A great climb](/uploads/a.jpg "Jane Doe | https://example.com/jane")');
    check(str_contains($md, '<figcaption>A great climb') && str_contains($md, 'Jane Doe') && str_contains($md, 'href="https://example.com/jane"'), 'markdown image title becomes a photo credit');
    check(str_contains(Markdown::render('![](/uploads/a.jpg "Only Name")'), '<figcaption>'), 'credit alone makes a caption');
    check(!str_contains(Markdown::render('![x](/uploads/a.jpg "Evil | javascript:alert(1)")'), 'javascript:'), 'unsafe link in a markdown credit is dropped');
    check(!str_contains(Markdown::render('![x](/uploads/a.jpg)'), 'Photo:'), 'no credit when none is given');

    Db::insert('media', ['image' => 'uploads/credit-test.jpg', 'caption' => 'Test', 'pos' => 99, 'credit' => 'Sam Shot', 'credit_url' => 'https://example.org/sam']);
    Settings::set('hero_image', 'uploads/hero-test.jpg');
    Settings::set('hero_credit', 'Hero Photographer');
    Db::run("UPDATE posts SET cover = 'uploads/cover-test.jpg', cover_credit = 'Cover Person', cover_credit_url = 'https://example.org/cover' WHERE slug = 'welcome'");
    $home = Site::response('/')['body'];
    check(str_contains($home, 'Sam Shot') && str_contains($home, 'href="https://example.org/sam"'), 'gallery photo shows its credit with link');
    check(str_contains($home, 'Hero Photographer'), 'main photo shows its credit');
    check(str_contains(Site::response('/blog/welcome')['body'], 'Cover Person'), 'blog cover shows its credit');
    Settings::set('hero_credit', '');
    check(!str_contains(Site::response('/')['body'], 'Hero Photographer'), 'no credit when the field is empty');
    Db::run("DELETE FROM media WHERE image = 'uploads/credit-test.jpg'");
    Settings::set('hero_image', '');
    Db::run("UPDATE posts SET cover = '', cover_credit = '', cover_credit_url = '' WHERE slug = 'welcome'");

    echo "Headings and partner levels\n";
    $body = Site::response('/')['body'];
    check(str_contains($body, '<h2>Partners</h2>') && str_contains($body, 'class="tier">Title partner<'), 'default headings and level names');
    Settings::setMany(['sec_sponsors_title' => 'Our supporters', 'sec_sponsors_kicker' => 'With thanks', 'tier_title_label' => 'Main sponsor']);
    $body = Site::response('/')['body'];
    check(substr_count($body, 'Our supporters') >= 2, 'custom heading is used in the section and in the menu');
    check(str_contains($body, 'With thanks') && str_contains($body, 'Main sponsor'), 'custom small label and level name are shown');
    check(!str_contains($body, '<h2>Partners</h2>'), 'the default heading is replaced');
    Settings::setMany(['sec_sponsors_show_title' => '0', 'sec_sponsors_show_kicker' => '0', 'tier_title_show' => '0']);
    $body = Site::response('/')['body'];
    check(str_contains($body, '<h2 class="sr-only">Our supporters</h2>'), 'a switched-off heading stays in the page for screen readers');
    check(!str_contains($body, 'With thanks') && !str_contains($body, 'Main sponsor'), 'switched-off label and level name are not shown');
    check(str_contains($body, 'sec-h bare'), 'a heading row without anything visible collapses');
    Settings::setMany(['sec_sponsors_title' => '', 'sec_sponsors_kicker' => '', 'sec_sponsors_show_title' => '1', 'sec_sponsors_show_kicker' => '1', 'tier_title_label' => '', 'tier_title_show' => '1']);
    check(str_contains(Site::response('/')['body'], '<h2>Partners</h2>'), 'empty texts fall back to the defaults');

    echo "Pages and legal templates\n";
    check(Legal::render('A {{#if x}}B {{#if y}}C{{/if}}{{/if}}D', [], ['x' => true, 'y' => false]) === "A B D\n", 'nested conditionals work');
    check(Legal::render('{{#unless x}}no{{/unless}}{{#if x}}yes{{/if}}', [], ['x' => true]) === "yes\n", 'unless works');
    check(Legal::render("Phone: {{phone}}\nEmail: {{email}}", ['phone' => '', 'email' => 'a@b.c'], []) === "Email: a@b.c\n", 'a line with an empty value is left out');
    check(Legal::render("- a\n{{#if x}}\n- b\n{{/if}}\n- c", [], ['x' => true]) === "- a\n- b\n- c\n", 'list items stay together after conditionals');
    check(Legal::slugError('admin') !== null && Legal::slugError('Foo Bar') !== null && Legal::slugError('impressum') === null, 'page addresses are validated');

    $bad = [];
    foreach (Legal::regions() as $rid => $reg) {
        if (Legal::langs($rid) === []) {
            $bad[] = "$rid has no language";
        }
        foreach (Legal::langs($rid) as $lang) {
            foreach ($reg['kinds'] as $kind) {
                $b = Legal::build($kind, $rid, $lang);
                if ($b === null || str_contains($b['body'], '{{') || strlen($b['body']) < 60 || !preg_match('/^# /', $b['body'])) {
                    $bad[] = "$rid/$lang/$kind";
                }
            }
        }
        foreach ($reg['needs'] as $need) {
            if (count($need) !== 3 || !in_array($need[1], ['required', 'likely', 'recommended'], true)) {
                $bad[] = "$rid needs entry";
            }
        }
    }
    check($bad === [], 'every region has templates in every offered language, with all placeholders resolved', implode(', ', $bad));

    Settings::setMany(['legal_name' => 'Pat Example', 'legal_street' => 'Example Street 1', 'legal_city' => '12345 Sampletown', 'legal_email' => 'pat@example.org', 'legal_phone' => '', 'legal_f_contact_form' => '1', 'legal_f_youtube' => '0']);
    Db::run('DELETE FROM pages');
    $res = Legal::create('dach', 'de', false);
    check($res['created'] === ['impressum', 'datenschutz', 'werbung'], 'DACH creates Impressum, Datenschutz and Werbung', implode(',', $res['created']));
    check(Site::response('/impressum')['status'] === 404, 'a draft page is not public');
    $imp = (string)Db::val("SELECT body FROM pages WHERE slug = 'impressum'");
    check(str_contains($imp, 'Pat Example') && str_contains($imp, 'Example Street 1') && !str_contains($imp, 'Telefon'), 'details are filled in and an empty phone line is left out');
    $priv = (string)Db::val("SELECT body FROM pages WHERE slug = 'datenschutz'");
    check(str_contains($priv, 'Kontaktformular') && !str_contains($priv, 'youtube-nocookie'), 'privacy text follows the chosen features');
    Db::run("UPDATE pages SET status = 'published'");
    $r = Site::response('/impressum');
    check($r['status'] === 200 && str_contains($r['body'], 'Example Street 1') && str_contains($r['body'], 'noindex'), 'a published legal page is shown, with noindex');
    check(str_contains(Site::response('/')['body'], 'href="/datenschutz"'), 'published pages are linked in the footer on every page');
    check(!str_contains(Site::response('/sitemap.xml')['body'], '/impressum'), 'legal pages stay out of the sitemap');

    Db::run("UPDATE pages SET body = 'my own words' WHERE slug = 'datenschutz'");
    Settings::set('legal_f_youtube', '1');
    $res = Legal::create('dach', 'de', false);
    check($res['created'] === [] && count($res['skipped']) === 3 && Db::val("SELECT body FROM pages WHERE slug = 'datenschutz'") === 'my own words', 'existing pages are not overwritten by default');
    $res = Legal::create('dach', 'de', true);
    check(count($res['updated']) === 3 && str_contains((string)Db::val("SELECT body FROM pages WHERE slug = 'datenschutz'"), 'youtube-nocookie'), 'replace updates template pages with the new settings');
    Db::insert('pages', ['slug' => 'privacy', 'title' => 'Mine', 'body' => 'written by me', 'status' => 'published', 'in_footer' => 0, 'pos' => 9, 'template' => '', 'updated_at' => now()]);
    $res = Legal::create('eu', 'en', true);
    check(in_array('privacy', $res['skipped'], true) && Db::val("SELECT body FROM pages WHERE slug = 'privacy'") === 'written by me', 'a page the owner wrote is never replaced, even with replace on');
    check(str_contains(Site::response('/sitemap.xml')['body'], '/privacy'), 'own pages are in the sitemap');
    Db::run('DELETE FROM pages');
    Legal::create('uk', 'en', false);
    check(str_contains((string)Db::val("SELECT body FROM pages WHERE slug = 'privacy'"), 'Information Commissioner'), 'UK privacy notice names the ICO');
    Db::run('DELETE FROM pages');
    $res = Legal::create('us', 'en', false);
    check(!in_array('legal-notice', $res['created'], true) && in_array('privacy', $res['created'], true), 'US needs no legal notice');
    Db::run('DELETE FROM pages');
    Settings::setMany(['legal_f_contact_form' => '', 'legal_f_youtube' => '']);

    echo "Sessions and QR login\n";
    check(Auth::device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1') === 'Safari on iPhone', 'device label: Safari on iPhone');
    check(Auth::device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0') === 'Edge on Windows', 'device label: Edge on Windows');
    check(Auth::device('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36') === 'Chrome on Mac', 'device label: Chrome on Mac');
    check(Auth::device('') === 'Unknown device', 'device label for an empty user agent');

    foreach (['Safari', 'Firefox', 'Chrome'] as $i => $b) {
        Db::insert('login_sessions', ['user_id' => $uid, 'token_hash' => hash('sha256', 'tok' . $i), 'ip' => '198.51.100.' . $i, 'ua' => $b . '/1 Linux', 'method' => $i === 1 ? 'qr' : 'password', 'created_at' => now(), 'last_seen' => now()]);
    }
    $list = Auth::sessionsFor($uid);
    check(count($list) === 3 && $list[0]['device'] !== '' && $list[0]['current'] === false, 'logged-in devices are listed with a device label');
    Auth::revoke($uid, (int)$list[0]['id']);
    check(count(Auth::sessionsFor($uid)) === 2, 'one device can be logged out');
    Auth::revoke($uid + 99, (int)$list[1]['id']);
    check(count(Auth::sessionsFor($uid)) === 2, 'a session can only be ended by its own user');
    Auth::revokeOthers($uid);
    check(count(Auth::sessionsFor($uid)) === 0, 'all other devices can be logged out');

    check(is_file($tmp . '/assets/qrcode.js') && str_contains((string)file_get_contents($tmp . '/assets/qrcode.js'), 'createSvgTag'), 'the QR code library is part of the package');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
    check(QrLogin::create($uid) === null, 'no QR code can be made while QR login is off for the user');
    Db::run('UPDATE users SET qr_enabled = 1 WHERE id = ?', [$uid]);
    $q = QrLogin::create($uid);
    $tok = is_array($q) ? substr($q['url'], strrpos($q['url'], 't=') + 2) : '';
    check(is_array($q) && strlen($tok) === 64 && $q['ttl'] === 20 && str_contains($q['url'], '/admin/qr/login?t='), 'a logged-in user gets a code with a 64-character token that is valid for 20 seconds');
    check(Db::val('SELECT token_hash FROM qr_tokens WHERE id = ?', [$q['id']]) === hash('sha256', $tok) && Db::val('SELECT COUNT(*) FROM qr_tokens WHERE token_hash = ?', [$tok]) == 0, 'only a hash of the token is stored');
    check(QrLogin::state($q['id'], $uid) === 'waiting' && QrLogin::state($q['id'], $uid + 99) === 'expired', 'the computer sees "waiting", and nobody else can look at the code');
    check(QrLogin::open(str_repeat('a', 64)) === null && QrLogin::consume(str_repeat('a', 64)) === null && QrLogin::open('nonsense') === null, 'a wrong token does nothing');
    check(QrLogin::open($tok) !== null && QrLogin::state($q['id'], $uid) === 'opened', 'opening the address moves the code to the confirm step');
    check(QrLogin::consume($tok) === $uid, 'the tap logs the user in');
    check(QrLogin::consume($tok) === null && QrLogin::open($tok) === null && QrLogin::state($q['id'], $uid) === 'used', 'a code works only once');

    $q = QrLogin::create($uid);
    $tok = substr($q['url'], strrpos($q['url'], 't=') + 2);
    Db::run('UPDATE qr_tokens SET created = ? WHERE id = ?', [time() - 30, $q['id']]);
    check(QrLogin::state($q['id'], $uid) === 'expired' && QrLogin::open($tok) === null && QrLogin::consume($tok) === null, 'a code older than 20 seconds is dead');

    $q = QrLogin::create($uid);
    $tok = substr($q['url'], strrpos($q['url'], 't=') + 2);
    QrLogin::open($tok);
    Db::run('UPDATE qr_tokens SET created = ? WHERE id = ?', [time() - 60, $q['id']]);
    check(QrLogin::consume($tok) === $uid, 'after the code was opened in time, the phone has a little longer to tap');

    $q = QrLogin::create($uid);
    $tok = substr($q['url'], strrpos($q['url'], 't=') + 2);
    QrLogin::open($tok);
    Db::run('UPDATE qr_tokens SET opened = ? WHERE id = ?', [time() - 60, $q['id']]);
    check(QrLogin::consume($tok) === null, 'but not for ever after it was opened');

    $q = QrLogin::create($uid);
    $tok = substr($q['url'], strrpos($q['url'], 't=') + 2);
    Db::run('UPDATE users SET qr_enabled = 0 WHERE id = ?', [$uid]);
    check(QrLogin::open($tok) === null && QrLogin::consume($tok) === null, 'switching QR login off kills open codes');
    Db::run('UPDATE users SET qr_enabled = 1 WHERE id = ?', [$uid]);
    QrLogin::revokeAll($uid);
    check((int)Db::val('SELECT COUNT(*) FROM qr_tokens WHERE user_id = ?', [$uid]) === 0, 'all open codes of a user can be removed');

    $made = 0;
    for ($i = 0; $i < 8; $i++) {
        $made += QrLogin::create($uid) !== null ? 1 : 0;
    }
    check($made === 5, 'at most five codes per ten minutes per user');
    Db::run('DELETE FROM qr_tokens');
    Db::run('UPDATE users SET qr_enabled = 0');

    echo "Statistics and cookie notice\n";
    check(Analytics::enabled(), 'statistics are on after a fresh install');
    Db::run('DELETE FROM hits');
    Db::run('DELETE FROM hit_days');
    $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
    $_SERVER['HTTP_USER_AGENT'] = $ua;
    $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    $_SERVER['HTTP_REFERER'] = 'https://www.instagram.com/some/post';
    Analytics::page('/');
    $row = Db::row('SELECT * FROM hits');
    check($row && $row['path'] === '/' && $row['ref'] === 'instagram.com' && $row['dev'] === 'phone' && $row['br'] === 'Safari' && strlen($row['vh']) === 16, 'a visit is stored with page, referrer domain, device and browser');
    check(!str_contains((string)json_encode($row), '203.0.113.7') && !str_contains((string)json_encode($row), 'iPhone'), 'neither the IP address nor the full browser text is stored');
    Analytics::page('/blog');
    check((int)Db::val('SELECT COUNT(*) FROM hits') === 2 && (int)Db::val('SELECT COUNT(DISTINCT vh) FROM hits') === 1, 'one visitor on one day has one code');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.8';
    Analytics::page('/');
    check((int)Db::val('SELECT COUNT(DISTINCT vh) FROM hits') === 2, 'another visitor gets another code');
    $_SERVER['HTTP_REFERER'] = 'http://localhost/blog';
    Analytics::page('/blog/welcome');
    check(Db::val("SELECT ref FROM hits WHERE path = '/blog/welcome'") === '', 'a referrer from the own site is not counted as a source');

    $count = static fn(): int => (int)Db::val('SELECT COUNT(*) FROM hits');
    $n = $count();
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
    Analytics::page('/');
    check($count() === $n, 'bots are not counted');
    $_SERVER['HTTP_USER_AGENT'] = $ua;
    $_SERVER['HTTP_DNT'] = '1';
    Analytics::page('/');
    unset($_SERVER['HTTP_DNT']);
    $_SERVER['HTTP_SEC_GPC'] = '1';
    Analytics::page('/');
    unset($_SERVER['HTTP_SEC_GPC']);
    $_SERVER['HTTP_SEC_PURPOSE'] = 'prefetch';
    Analytics::page('/');
    unset($_SERVER['HTTP_SEC_PURPOSE']);
    check($count() === $n, 'Do Not Track, Global Privacy Control and prefetch requests are not counted');
    Settings::set('stats_on', '0');
    Analytics::page('/');
    Settings::set('stats_on', '1');
    check($count() === $n, 'nothing is counted while statistics are off');

    Analytics::click('sponsor:Example Partner', '/');
    Analytics::click('social:instagram', '/');
    Analytics::click('video:play', '/blog/welcome');
    $clicks = (int)Db::val("SELECT COUNT(*) FROM hits WHERE kind = 'click'");
    Analytics::click('sponsor:No Such Sponsor', '/');
    Analytics::click('social:tiktok', '/');
    Analytics::click('evil:thing', '/');
    Analytics::click('sponsor:' . str_repeat('x', 200), '/');
    check($clicks === 3 && (int)Db::val("SELECT COUNT(*) FROM hits WHERE kind = 'click'") === 3, 'clicks are counted only for sponsors, social accounts and the video that exist');

    $rep = Analytics::report(7);
    check($rep['views'] === 4 && $rep['visitors'] === 2, 'the report counts views and daily visitors', $rep['views'] . '/' . $rep['visitors']);
    check($rep['pages'][0]['k'] === '/' && (int)$rep['pages'][0]['n'] === 2, 'top pages are listed by views');
    check($rep['referrers'][0]['k'] === 'instagram.com' && (int)$rep['referrers'][0]['n'] === 3, 'top sources are listed');
    check($rep['devices'][0]['k'] === 'phone' && count($rep['clicks']) === 3 && count($rep['series']) === 7, 'devices, clicks and one entry per day are reported');
    check(Analytics::csv('days', 7)[6][1] === 4, 'the CSV rows hold the daily totals');

    Db::run("UPDATE sponsors SET url = 'https://example.org/partner' WHERE name = 'Example Partner'");
    $home = Site::response('/')['body'];
    check(str_contains($home, 'data-t="sponsor:Example Partner"') && str_contains($home, 'data-t="social:instagram"') && str_contains($home, 'data-beacon="/t"'), 'links carry a tracking label and the script gets the beacon address while statistics are on');
    Settings::set('stats_on', '0');
    check(!str_contains(Site::response('/')['body'], 'data-beacon'), 'no beacon address while statistics are off');
    Db::run("UPDATE sponsors SET url = '' WHERE name = 'Example Partner'");
    Settings::set('stats_on', '1');

    Db::run("UPDATE hits SET day = ? WHERE kind = 'page'", [date('Y-m-d', strtotime('-200 days'))]);
    Analytics::prune();
    check((int)Db::val("SELECT COUNT(*) FROM hits WHERE kind = 'page'") === 0 && (int)Db::val('SELECT views FROM hit_days') === 4 && (int)Db::val('SELECT visitors FROM hit_days') === 2, 'old details are folded into daily totals and deleted');
    check(Analytics::report(90)['views'] === 0, 'days outside the range are not reported');
    Analytics::wipe();
    check((int)Db::val('SELECT COUNT(*) FROM hits') + (int)Db::val('SELECT COUNT(*) FROM hit_days') === 0, 'all statistics can be deleted');

    Settings::set('stats_on', '1');
    check(str_contains(Legal::build('privacy-gdpr', 'dach', 'en')['body'], 'Visit statistics') && str_contains(Legal::build('privacy-gdpr', 'dach', 'en')['body'], 'after 90 days'), 'the privacy policy describes the statistics while they are on');
    Settings::set('stats_on', '0');
    check(!str_contains(Legal::build('privacy-gdpr', 'dach', 'de')['body'], 'Besuchsstatistik'), 'and leaves them out while they are off');
    Settings::set('stats_on', '1');
    unset($_SERVER['HTTP_REFERER']);

    check(!str_contains(Site::response('/')['body'], 'id="notice"'), 'the cookie notice is off by default');
    Settings::set('notice_on', '1');
    $b = Site::response('/')['body'];
    check(str_contains($b, 'id="notice"') && str_contains($b, 'does not use cookies for tracking or advertising') && str_contains($b, 'Anonymous visit statistics'), 'the notice shows the standard text and mentions the statistics');
    check(!str_contains($b, 'href="/privacy"'), 'no policy link without a published privacy policy');
    Db::insert('pages', ['slug' => 'privacy', 'title' => 'Privacy', 'body' => 'x', 'status' => 'published', 'in_footer' => 1, 'pos' => 1, 'template' => 'privacy-gdpr', 'updated_at' => now()]);
    check(str_contains(Site::response('/')['body'], 'class="notice"') && str_contains(Site::response('/')['body'], 'href="/privacy">Privacy policy</a>'), 'the notice links to the published privacy policy');
    $id1 = Site::notice()['id'];
    Settings::set('notice_text', 'Custom words about cookies.');
    check(str_contains(Site::response('/')['body'], 'Custom words about cookies.') && Site::notice()['id'] !== $id1, 'own text is used and makes the notice show again');
    Settings::setMany(['notice_on' => '0', 'notice_text' => '']);
    Db::run('DELETE FROM pages');

    echo "Two-factor login, security emails, forgot password\n";
    $rfc = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    check(Totp::code($rfc, intdiv(59, 30)) === '287082', 'one-time codes match the RFC 6238 test value at 59 seconds');
    check(Totp::code($rfc, intdiv(1111111109, 30)) === '081804' && Totp::code($rfc, intdiv(1234567890, 30)) === '005924', 'one-time codes match the other RFC 6238 test values');
    check(Totp::decode(Totp::encode("hello\x00world")) === "hello\x00world" && preg_match('/^[A-Z2-7]{32}$/', Totp::secret()) === 1, 'base32 round trip and secret format');
    $now = 1111111109;
    $stepNow = intdiv($now, 30);
    check(Totp::verify($rfc, '081804', 0, $now) === $stepNow && Totp::verify($rfc, '081 804', 0, $now) === $stepNow, 'a right code is accepted, also typed with a space');
    check(Totp::verify($rfc, '081804', $stepNow, $now) === null, 'a code can not be used twice');
    check(Totp::verify($rfc, '081804', 0, $now + 30) === $stepNow && Totp::verify($rfc, '081804', 0, $now + 90) === null, 'a clock difference of one step is tolerated, more is not');
    check(Totp::verify($rfc, '12345', 0, $now) === null && Totp::verify($rfc, '000000', 0, $now) === null, 'short and wrong codes are refused');
    check(str_contains(Totp::uri('me@example.com', 'My Site', $rfc), 'otpauth://totp/My%20Site%3Ame%40example.com?secret=' . $rfc), 'the app address carries the account, issuer and secret');

    $sealed = Crypto::seal('secret value');
    check(Crypto::open($sealed) === 'secret value' && Crypto::seal('secret value') !== $sealed, 'secrets are encrypted, with a fresh nonce every time');
    check(Crypto::open(substr($sealed, 0, -4) . 'AAAA') === null && Crypto::open('garbage') === null, 'changed or invalid ciphertext is refused');

    $mails = [];
    Mailer::$transport = static function (array $m) use (&$mails): array {
        $mails[] = $m;
        return [true, ''];
    };
    $secret = Totp::secret();
    $cur = intdiv(time(), 30);
    $codes = TwoFactor::enable($uid, $secret, Totp::verify($secret, Totp::code($secret, $cur)));
    check(count($codes) === 10 && preg_match('/^[A-Z2-7]{5}-[A-Z2-7]{5}$/', $codes[0]) === 1 && TwoFactor::enabled(Users::find($uid)), 'switching on gives ten recovery codes');
    check(!str_contains((string)Db::val('SELECT totp_secret FROM users WHERE id = ?', [$uid]), $secret) && Db::val('SELECT code_hash FROM recovery_codes LIMIT 1') !== str_replace('-', '', $codes[0]), 'secret and recovery codes are not stored in the clear');
    check(TwoFactor::check($uid, Totp::code($secret, $cur + 1), false), 'a current app code logs in');
    check(!TwoFactor::check($uid, Totp::code($secret, $cur + 1), false), 'the same app code does not work twice');
    check(!TwoFactor::check($uid, '123456', true) && !TwoFactor::check($uid, 'AAAAA-AAAAA', true), 'wrong codes are refused');
    check(!TwoFactor::check($uid, $codes[1], false), 'recovery codes are refused where only app codes are allowed');
    check(TwoFactor::check($uid, strtolower($codes[0]), true) && !TwoFactor::check($uid, $codes[0], true) && TwoFactor::codesLeft($uid) === 9, 'a recovery code works once, in lower case too');
    check(count($mails) === 1 && str_contains($mails[0]['subject'], 'recovery code'), 'using a recovery code sends an email');
    $new = TwoFactor::newCodes($uid);
    check(count($new) === 10 && !TwoFactor::check($uid, $codes[2], true) && TwoFactor::check($uid, $new[0], true), 'new recovery codes replace the old ones');
    TwoFactor::disable($uid);
    check(!TwoFactor::enabled(Users::find($uid)) && TwoFactor::codesLeft($uid) === 0, 'switching off removes the secret and the codes');

    $mails = [];
    Db::run('DELETE FROM login_devices');
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
    $_SERVER['REMOTE_ADDR'] = '198.51.100.77';
    Notify::login($uid, 'password');
    Notify::login($uid, 'password');
    check(count($mails) === 0 && (int)Db::val('SELECT COUNT(*) FROM login_devices WHERE user_id = ?', [$uid]) === 1, 'the first login and logins from a known device send no email');
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
    Notify::login($uid, 'password');
    check(count($mails) === 1 && $mails[0]['to'] === 'admin@example.com' && str_contains($mails[0]['subject'], 'new device'), 'a login from a new device sends an email');
    check(str_contains($mails[0]['body'], 'Safari on iPhone') && str_contains($mails[0]['body'], '198.51.100.77'), 'the email names the device and the IP address');
    Notify::login($uid, 'qr');
    check(count($mails) === 2 && str_contains($mails[1]['subject'], 'QR code'), 'a QR login always sends an email');
    Db::run('UPDATE users SET notify = 0 WHERE id = ?', [$uid]);
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0';
    Notify::login($uid, 'password');
    check(count($mails) === 2, 'the new-device email can be switched off');
    Notify::login($uid, 'qr');
    Notify::event($uid, 'password_changed');
    Notify::event($uid, 'nonsense');
    check(count($mails) === 4 && str_contains($mails[3]['subject'], 'password was changed'), 'QR and security-change emails can not be switched off');
    Db::run('UPDATE users SET notify = 1 WHERE id = ?', [$uid]);

    $mails = [];
    PasswordReset::request('nobody@example.com', '198.51.100.1');
    PasswordReset::request('not an email', '198.51.100.1');
    check(count($mails) === 0, 'an unknown address gets no email, and nothing is revealed');
    PasswordReset::request('Admin@Example.com', '198.51.100.1');
    check(count($mails) === 1 && preg_match('/admin\/reset\?t=([a-f0-9]{64})/', $mails[0]['body'], $tm) === 1, 'a known address gets a link');
    $tok = $tm[1];
    check((int)Db::val('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$tok]) === 0 && PasswordReset::find($tok) !== null && PasswordReset::find('abc') === null, 'only a hash of the token is stored');
    check(str_contains((string)PasswordReset::finish($tok, 'short'), '10 characters') && PasswordReset::find($tok) !== null, 'a too short password does not use up the link');
    Db::insert('login_sessions', ['user_id' => $uid, 'token_hash' => hash('sha256', 'x1'), 'ip' => '1.1.1.1', 'ua' => 'x', 'method' => 'password', 'created_at' => now(), 'last_seen' => now()]);
    check(PasswordReset::finish($tok, 'a-brand-new-password') === null, 'the link sets a new password');
    check(password_verify('a-brand-new-password', (string)Db::val('SELECT password_hash FROM users WHERE id = ?', [$uid])), 'the new password works');
    check(PasswordReset::find($tok) === null && PasswordReset::finish($tok, 'yet-another-password') !== null, 'a link works only once');
    check(count(Auth::sessionsFor($uid)) === 0 && count($mails) === 2 && str_contains($mails[1]['subject'], 'reset'), 'all devices are logged out and the user is told');
    PasswordReset::request('admin@example.com', '198.51.100.2');
    Db::run('UPDATE password_resets SET created = ?', [time() - 4000]);
    preg_match('/t=([a-f0-9]{64})/', end($mails)['body'], $tm2);
    check(PasswordReset::find($tm2[1]) === null, 'a link is dead after 60 minutes');
    Db::run('DELETE FROM password_resets');
    $mails = [];
    for ($i = 0; $i < 6; $i++) {
        PasswordReset::request('admin@example.com', '198.51.100.3');
    }
    check(count($mails) === 3, 'at most three links per hour for one account', (string)count($mails));
    Db::run('DELETE FROM password_resets');

    $f = $tmp . '/storage/RESET_ADMIN';
    @unlink($f);
    check(!Recovery::active(), 'recovery mode is off without the file');
    file_put_contents($f, 'short');
    check(!Recovery::active(), 'a phrase shorter than 10 characters does not switch it on');
    file_put_contents($f, 'correct horse battery');
    check(Recovery::active() && Recovery::run('wrong phrase here', 'admin@example.com', 'long-enough-password-1', '198.51.100.9') !== null, 'a wrong phrase is refused');
    check(str_contains((string)Recovery::run('correct horse battery', 'nobody@example.com', 'long-enough-password-1', '198.51.100.9'), 'No account'), 'the phrase must be followed by a real account');
    TwoFactor::enable($uid, Totp::secret(), 1);
    $mails = [];
    check(Recovery::run('correct horse battery', 'Admin@Example.com', 'long-enough-password-1', '198.51.100.9') === null, 'the right phrase resets the password');
    check(password_verify('long-enough-password-1', (string)Db::val('SELECT password_hash FROM users WHERE id = ?', [$uid])) && !TwoFactor::enabled(Users::find($uid)) && !is_file($f), 'two-factor login is off, the file is deleted');
    check(count($mails) === 1 && str_contains($mails[0]['subject'], 'reset from the server'), 'the user is told about it');
    file_put_contents($f, 'correct horse battery');
    touch($f, time() - 1000);
    check(!Recovery::active() && !is_file($f), 'an old file is ignored and removed');
    Mailer::$transport = null;
    unset($_SERVER['HTTP_USER_AGENT']);
    $_SERVER['REMOTE_ADDR'] = '203.0.113.50';

    echo "Images\n";
    $png = $tmp . '/test.png';
    $im = imagecreatetruecolor(2400, 1600);
    imagepng($im, $png);
    $rel = Images::process($png);
    check(is_file($tmp . '/' . $rel) && is_file($tmp . '/' . Images::thumb($rel)), 'upload creates large and thumb files');
    [$w] = getimagesize($tmp . '/' . $rel);
    check($w === 1800, 'large image is resized to 1800px');
    file_put_contents($tmp . '/fake.png', '<?php echo 1;');
    try {
        Images::process($tmp . '/fake.png');
        check(false, 'non-image is rejected');
    } catch (RuntimeException) {
        check(true, 'non-image is rejected');
    }
    delete_upload($rel);
    check(!is_file($tmp . '/' . $rel), 'delete_upload removes files');

    echo "Update\n";
    $build = static function (string $version, array $extraFiles, string $name) use ($tmp): string {
        $stage = $tmp . '/stage-' . $name;
        rrm($stage);
        foreach (['app', 'themes', 'assets'] as $d) {
            rcopy($tmp . '/' . $d, $stage . '/' . $d);
        }
        copy($tmp . '/index.php', $stage . '/index.php');
        $man = json_decode((string)file_get_contents($tmp . '/manifest.json'), true);
        $man['version'] = $version;
        file_put_contents($stage . '/manifest.json', json_encode($man));
        foreach ($extraFiles as $path => $content) {
            @mkdir(dirname($stage . '/' . $path), 0755, true);
            file_put_contents($stage . '/' . $path, $content);
        }
        $zipFile = $tmp . '/' . $name . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $f) {
            $zip->addFile($f->getPathname(), str_replace('\\', '/', substr($f->getPathname(), strlen($stage) + 1)));
        }
        $zip->close();
        return $zipFile;
    };

    $good = $build('0.2.0', [
        'app/migrations/0900_test_note.php' => "<?php\nreturn static function (): void { Db::addColumn('posts', 'note', \"VARCHAR(100) NOT NULL DEFAULT ''\"); };\n",
    ], 'good');
    $r = Updater::applyLocal($good, '0.2.0');
    check($r['ok'], 'update package applied', $r['error']);
    check(is_file($tmp . '/app/migrations/0900_test_note.php'), 'new code is in place');
    check(Updater::version() === '0.2.0', 'manifest shows the new version');
    check(Site::response('/')['status'] === 503, 'site shows maintenance while update is pending');
    $f = Updater::finish();
    check($f['ok'], 'update finishes', $f['message']);
    check(Db::hasColumn('posts', 'note'), 'migration ran');
    check(Site::response('/')['status'] === 200, 'site is back after the update');
    check(count(Updater::backups()) >= 2, 'backups were written');
    check(Db::val('SELECT COUNT(*) FROM posts') > 0, 'content survived the update');

    $bad = $build('0.3.0', [
        'app/migrations/0901_test_breaks.php' => "<?php\nreturn static function (): void { throw new RuntimeException('boom'); };\n",
    ], 'bad');
    $r = Updater::applyLocal($bad, '0.3.0');
    check($r['ok'], 'bad package applied (to test rollback)', $r['error']);
    $f = Updater::finish();
    check(!$f['ok'], 'failing migration is reported');
    check(!is_file($tmp . '/app/migrations/0901_test_breaks.php'), 'rollback removed the new code');
    check(Updater::version() === '0.2.0', 'rollback restored the previous version');
    check(!is_file($tmp . '/storage/update-pending.json'), 'pending flag cleared');
} catch (Throwable $e) {
    $fail++;
    echo "  FAIL unexpected exception: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
}

rrm($tmp);
echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
