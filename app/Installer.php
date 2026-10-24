<?php
declare(strict_types=1);

/** First-run setup at /install. Disappears once storage/config.php exists. */
final class Installer
{
    public static function dispatch(string $path, string $method): void
    {
        if ($path !== '/install') {
            redirect('/install');
        }
        rp_session();
        send_headers(true);

        $checks = self::checks();
        $blocked = (bool)array_filter($checks, static fn($c) => !$c['ok'] && $c['blocking']);
        $errors = [];
        $old = [
            'site_name' => '', 'lang' => 'en', 'preset' => 'rally', 'timezone' => date_default_timezone_get(),
            'db_driver' => in_array('sqlite', PDO::getAvailableDrivers(), true) ? 'sqlite' : 'mysql',
            'mysql_host' => 'localhost', 'mysql_port' => '3306', 'mysql_name' => '', 'mysql_user' => '', 'mysql_pass' => '',
            'admin_email' => '', 'example' => '1',
        ];

        if ($method === 'POST' && !$blocked) {
            csrf_check();
            foreach ($old as $k => $_) {
                $old[$k] = trim((string)($_POST[$k] ?? ''));
            }
            $old['example'] = !empty($_POST['example']) ? '1' : '';
            $pw = (string)($_POST['admin_password'] ?? '');
            $pw2 = (string)($_POST['admin_password2'] ?? '');
            $errors = self::validate($old, $pw, $pw2);
            if (!$errors) {
                try {
                    $uid = self::install($old, $pw);
                    Auth::login($uid);
                    flash('Your page is ready. Start by filling in your profile.');
                    redirect('/admin/profile');
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        echo tpl('app/views/install/form', [
            'checks' => $checks, 'blocked' => $blocked, 'errors' => $errors, 'v' => $old,
            'presets' => Presets::all(), 'timezones' => DateTimeZone::listIdentifiers(),
            'drivers' => PDO::getAvailableDrivers(),
        ]);
    }

    public static function checks(): array
    {
        $w = static function (string $dir): bool {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            return is_dir($dir) && is_writable($dir);
        };
        $drivers = PDO::getAvailableDrivers();
        return [
            ['label' => 'PHP 8.1 or newer', 'ok' => PHP_VERSION_ID >= 80100, 'blocking' => true, 'detail' => 'PHP ' . PHP_VERSION],
            ['label' => 'Database driver (SQLite or MySQL)', 'ok' => in_array('sqlite', $drivers, true) || in_array('mysql', $drivers, true), 'blocking' => true, 'detail' => $drivers ? implode(', ', $drivers) : 'none found'],
            ['label' => 'Folder storage/ is writable', 'ok' => $w(RP_STORAGE), 'blocking' => true, 'detail' => 'Holds the settings, database and backups'],
            ['label' => 'Folder uploads/ is writable', 'ok' => $w(RP_UPLOADS), 'blocking' => true, 'detail' => 'Holds your images'],
            ['label' => 'mbstring', 'ok' => extension_loaded('mbstring'), 'blocking' => true, 'detail' => 'Text handling'],
            ['label' => 'GD image library', 'ok' => Images::available(), 'blocking' => false, 'detail' => 'Needed to upload images. Without it you can still write text.'],
            ['label' => 'Zip and download support', 'ok' => class_exists('ZipArchive') && (function_exists('curl_init') || ini_get('allow_url_fopen')), 'blocking' => false, 'detail' => 'Needed for one-click updates. Without it you can update by hand.'],
        ];
    }

    private static function validate(array $in, string $pw, string $pw2): array
    {
        $e = [];
        if ($in['site_name'] === '') {
            $e[] = 'Enter your name or the name of the page.';
        }
        if (!filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $e[] = 'Enter a valid email address for the admin account.';
        }
        if (strlen($pw) < 10) {
            $e[] = 'The password needs at least 10 characters.';
        }
        if ($pw !== $pw2) {
            $e[] = 'The two passwords are not the same.';
        }
        if (!isset(Presets::all()[$in['preset']])) {
            $e[] = 'Choose a design.';
        }
        if (!isset(Lang::AVAILABLE[$in['lang']])) {
            $e[] = 'Choose a language.';
        }
        if (!in_array($in['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $e[] = 'Choose a time zone.';
        }
        if ($in['db_driver'] === 'mysql' && ($in['mysql_name'] === '' || $in['mysql_user'] === '')) {
            $e[] = 'Enter the database name and user.';
        }
        return $e;
    }

    private static function install(array $in, string $password): int
    {
        foreach (['', '/logs', '/sessions', '/backups', '/tmp'] as $d) {
            if (!is_dir(RP_STORAGE . $d) && !@mkdir(RP_STORAGE . $d, 0755, true)) {
                throw new RuntimeException('Could not create the storage folder. Make storage/ writable and try again.');
            }
        }
        $drivers = PDO::getAvailableDrivers();
        if ($in['db_driver'] === 'mysql') {
            if (!in_array('mysql', $drivers, true)) {
                throw new RuntimeException('This server has no MySQL driver for PHP. Choose SQLite instead.');
            }
            $cfg = ['driver' => 'mysql', 'host' => $in['mysql_host'], 'port' => (int)$in['mysql_port'], 'name' => $in['mysql_name'], 'user' => $in['mysql_user'], 'pass' => (string)($_POST['mysql_pass'] ?? '')];
        } else {
            if (!in_array('sqlite', $drivers, true)) {
                throw new RuntimeException('This server has no SQLite driver for PHP. Choose MySQL instead.');
            }
            $cfg = ['driver' => 'sqlite', 'path' => RP_STORAGE . '/rp-' . bin2hex(random_bytes(6)) . '.sqlite'];
        }
        $cfg += ['timezone' => $in['timezone'], 'secret' => bin2hex(random_bytes(32)), 'installed' => date('c')];

        try {
            Db::connect($cfg);
            Db::val('SELECT 1');
        } catch (PDOException $e) {
            throw new RuntimeException('Could not connect to the database. Check the details. (' . $e->getMessage() . ')');
        }
        if (in_array('users', Db::tables(), true) && (int)Db::val('SELECT COUNT(*) FROM users') > 0) {
            throw new RuntimeException('This database already contains a Rider Page. Use an empty database or remove the old tables first.');
        }

        date_default_timezone_set($in['timezone']);
        try {
            Migrator::run();
        } catch (Throwable $e) {
            throw new RuntimeException('Setting up the database failed: ' . $e->getMessage());
        }
        Settings::reset();
        $uid = Auth::create($in['admin_email'], $password);
        Settings::setMany([
            'name' => $in['site_name'], 'lang' => $in['lang'], 'preset' => $in['preset'],
            'email' => $in['admin_email'], 'installed_version' => Updater::version(),
        ]);
        Lang::reset();
        if ($in['example'] === '1') {
            Seed::example($in['lang']);
        }

        $file = RP_STORAGE . '/config.php';
        $php = "<?php\n// Written by the Rider Page installer. Keep this file private.\nreturn " . var_export($cfg, true) . ";\n";
        if (file_put_contents($file . '.tmp', $php) === false || !rename($file . '.tmp', $file)) {
            throw new RuntimeException('Could not write storage/config.php. Make the storage folder writable.');
        }
        @chmod($file, 0640);
        return $uid;
    }
}
