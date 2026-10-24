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
        'app/migrations/0002_add_tagline_note.php' => "<?php\nreturn static function (): void { Db::addColumn('posts', 'note', \"VARCHAR(100) NOT NULL DEFAULT ''\"); };\n",
    ], 'good');
    $r = Updater::applyLocal($good, '0.2.0');
    check($r['ok'], 'update package applied', $r['error']);
    check(is_file($tmp . '/app/migrations/0002_add_tagline_note.php'), 'new code is in place');
    check(Updater::version() === '0.2.0', 'manifest shows the new version');
    check(Site::response('/')['status'] === 503, 'site shows maintenance while update is pending');
    $f = Updater::finish();
    check($f['ok'], 'update finishes', $f['message']);
    check(Db::hasColumn('posts', 'note'), 'migration ran');
    check(Site::response('/')['status'] === 200, 'site is back after the update');
    check(count(Updater::backups()) >= 2, 'backups were written');
    check(Db::val('SELECT COUNT(*) FROM posts') > 0, 'content survived the update');

    $bad = $build('0.3.0', [
        'app/migrations/0003_breaks.php' => "<?php\nreturn static function (): void { throw new RuntimeException('boom'); };\n",
    ], 'bad');
    $r = Updater::applyLocal($bad, '0.3.0');
    check($r['ok'], 'bad package applied (to test rollback)', $r['error']);
    $f = Updater::finish();
    check(!$f['ok'], 'failing migration is reported');
    check(!is_file($tmp . '/app/migrations/0003_breaks.php'), 'rollback removed the new code');
    check(Updater::version() === '0.2.0', 'rollback restored the previous version');
    check(!is_file($tmp . '/storage/update-pending.json'), 'pending flag cleared');
} catch (Throwable $e) {
    $fail++;
    echo "  FAIL unexpected exception: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
}

rrm($tmp);
echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
