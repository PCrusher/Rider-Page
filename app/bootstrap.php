<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Rider Page needs PHP 8.1 or newer. This server runs PHP ' . PHP_VERSION . '.');
}

defined('RP_ROOT') || define('RP_ROOT', dirname(__DIR__));
define('RP_APP', RP_ROOT . '/app');
define('RP_STORAGE', RP_ROOT . '/storage');
define('RP_UPLOADS', RP_ROOT . '/uploads');

ini_set('display_errors', '0');
ini_set('memory_limit', '256M');
ini_set('log_errors', '1');
@mkdir(RP_STORAGE . '/logs', 0755, true);
ini_set('error_log', RP_STORAGE . '/logs/php-errors.log');

// Fatal errors (for example a missing PHP extension) would otherwise end in a blank page.
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Something went wrong</title>'
            . '<body style="font:16px/1.5 system-ui,sans-serif;max-width:36rem;margin:15vh auto;padding:0 1rem">'
            . '<h1 style="font-size:1.4rem">Something went wrong</h1>'
            . '<p>The page could not be shown. The details are in <code>storage/logs/php-errors.log</code>.</p></body>';
    }
});

foreach (['helpers', 'Db', 'Settings', 'Migrator', 'Auth', 'Roles', 'Users', 'Images', 'Markdown', 'Lang', 'Presets', 'Redirects', 'Legal', 'Mailer', 'Contact', 'Site', 'Admin', 'Installer', 'Seed', 'Updater'] as $__f) {
    require_once RP_APP . '/' . $__f . '.php';
}
unset($__f);

set_exception_handler(static function (Throwable $e): void {
    @mkdir(RP_STORAGE . '/logs', 0755, true);
    @file_put_contents(
        RP_STORAGE . '/logs/error.log',
        '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n",
        FILE_APPEND
    );
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $debug = false;
    try {
        $debug = (bool)(rp_config()['debug'] ?? false);
    } catch (Throwable) {
    }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Something went wrong</title>'
        . '<body style="font:16px/1.5 system-ui,sans-serif;max-width:36rem;margin:15vh auto;padding:0 1rem">'
        . '<h1 style="font-size:1.4rem">Something went wrong</h1>'
        . '<p>The page could not be shown. The details are in <code>storage/logs/error.log</code>.</p>'
        . ($debug ? '<pre style="white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>' : '')
        . '</body>';
});

function rp_config(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $file = RP_STORAGE . '/config.php';
        $cfg = is_file($file) ? (require $file) : null;
        if ($cfg !== null && !is_array($cfg)) {
            $cfg = null;
        }
    }
    return $cfg;
}

function rp_run(): void
{
    $cfg = rp_config();
    $path = rp_path();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($cfg === null) {
        Installer::dispatch($path, $method);
        return;
    }

    date_default_timezone_set($cfg['timezone'] ?? 'UTC');
    Db::connect($cfg);
    if (!is_file(RP_STORAGE . '/update-pending.json')) {
        Migrator::runIfNeeded();
    }

    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        Admin::dispatch($path, $method);
        return;
    }
    Site::dispatch($path, $method);
}
