<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Folder the site is installed in, relative to the domain ('' for the domain root). */
function rp_base(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }
    return $base;
}

function url(string $path = '/'): string
{
    return rp_base() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = RP_ROOT . '/assets/' . $path;
    return url('assets/' . $path) . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

/** Request path without the install folder, always starting with a slash and without a trailing one. */
function rp_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = is_string($uri) ? $uri : '/';
    $base = rp_base();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . trim(rawurldecode($uri), '/');
    return $uri === '/index.php' ? '/' : $uri;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** https://example.com plus the install folder, with no trailing slash. */
function site_origin(): string
{
    $custom = trim((string)Settings::get('site_url', ''));
    if ($custom !== '') {
        return rtrim($custom, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https://' : 'http://') . $host . rp_base();
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function rp_nonce(): string
{
    static $n = null;
    return $n ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

function send_headers(bool $admin = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    $style = $admin ? "'self' 'unsafe-inline'" : "'self' 'nonce-" . rp_nonce() . "'";
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src $style; script-src 'self'; "
        . "frame-src https://www.youtube-nocookie.com; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
    );
    if ($admin) {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }
}

function rp_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $dir = RP_STORAGE . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    session_name('rp_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rp_base() ?: '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    rp_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    rp_session();
    $ok = isset($_POST['_csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['_csrf']);
    if (!$ok) {
        http_response_code(400);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}

function flash(string $message, string $type = 'ok'): void
{
    rp_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    rp_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function t(string $key, array $vars = []): string
{
    return Lang::t($key, $vars);
}

/** Render app/views/..., themes/views/... etc. (path relative to the install folder, without .php). */
function tpl(string $file, array $vars = []): string
{
    $path = RP_ROOT . '/' . $file . '.php';
    $render = static function (string $__path, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        try {
            include $__path;
        } catch (Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }
        return (string)ob_get_clean();
    };
    return $render($path, $vars);
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function slugify(string $s): string
{
    $s = trim($s);
    if (function_exists('transliterator_transliterate')) {
        $s = (string)transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s);
    } else {
        $s = strtr(mb_strtolower($s, 'UTF-8'), [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'á' => 'a',
            'à' => 'a', 'ó' => 'o', 'ñ' => 'n', 'ç' => 'c', 'ø' => 'o', 'æ' => 'ae',
        ]);
    }
    $s = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s) ?? '');
    return trim($s, '-');
}

function hex_valid(?string $c): bool
{
    return is_string($c) && (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}

function safe_url(string $u): string
{
    $u = trim($u);
    if ($u === '') {
        return '';
    }
    $scheme = strtolower((string)parse_url($u, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    return filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
}

function youtube_id(string $u): ?string
{
    $u = trim($u);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $u)) {
        return $u;
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $u, $m)) {
        return $m[1];
    }
    return null;
}

function social_types(): array
{
    return [
        'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'strava' => 'Strava',
        'x' => 'X', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'twitch' => 'Twitch',
        'spotify' => 'Spotify', 'bluesky' => 'Bluesky', 'mastodon' => 'Mastodon', 'website' => 'Website',
    ];
}

/** "Photo: Name" with the name linked when a safe link is given. Empty when there is nothing to credit. */
function credit_html(string $name, string $url = '', string $class = 'credit'): string
{
    $name = trim($name);
    $url = safe_url($url);
    if ($name === '' && $url !== '') {
        $name = (string)preg_replace('/^www\./i', '', (string)parse_url($url, PHP_URL_HOST));
    }
    if ($name === '') {
        return '';
    }
    $label = $url !== '' ? '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($name) . '</a>' : e($name);
    return '<span class="' . e($class) . '">' . e(t('credit.photo')) . ' ' . $label . '</span>';
}

function format_date(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    if ($ts === false) {
        return '';
    }
    return Lang::code() === 'de' ? date('d.m.Y', $ts) : date('j M Y', $ts);
}

function upload_url(?string $rel): string
{
    return $rel ? url($rel) : '';
}

function delete_upload(?string $rel): void
{
    if (!$rel || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
        return;
    }
    foreach ([$rel, Images::thumb($rel)] as $r) {
        $f = RP_ROOT . '/' . $r;
        if (is_file($f)) {
            @unlink($f);
        }
    }
}
