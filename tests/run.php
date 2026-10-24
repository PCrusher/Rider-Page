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
    }
}

foreach (['app', 'themes', 'assets', 'storage', 'uploads'] as $d) {
    rcopy("$src/$d", "$tmp/$d");
}
copy("$src/index.php", "$tmp/index.php");
copy("$src/manifest.json", "$tmp/manifest.json");

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
