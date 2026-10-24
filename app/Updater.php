<?php
declare(strict_types=1);

/**
 * Updates the code from the latest GitHub release.
 *
 * What gets replaced: app/, themes/, assets/, index.php, manifest.json.
 * What is never touched: storage/ (config, database, backups) and uploads/.
 *
 * Steps: check release -> preflight -> download + verify checksum -> backup -> extract ->
 * swap folders -> (next request) run migrations -> health check, or roll back.
 */
final class Updater
{
    private const CODE_DIRS = ['app', 'themes', 'assets'];
    private const CODE_FILES = ['index.php', 'manifest.json'];
    private const CACHE_SECONDS = 21600;

    /* ---------- local info ---------- */

    public static function manifest(): array
    {
        $file = RP_ROOT . '/manifest.json';
        $m = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        return is_array($m) ? $m + ['version' => '0.0.0', 'min_php' => '8.1', 'breaking' => false, 'changelog' => '', 'warnings' => []]
            : ['version' => '0.0.0', 'min_php' => '8.1', 'breaking' => false, 'changelog' => '', 'warnings' => []];
    }

    public static function version(): string
    {
        return (string)self::manifest()['version'];
    }

    public static function repo(): string
    {
        $custom = trim((string)Settings::get('update_repo', ''));
        return $custom !== '' ? $custom : (string)(self::manifest()['repo'] ?? '');
    }

    public static function cached(): ?array
    {
        $raw = (string)Settings::get('update_cache', '');
        $c = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($c) ? $c : null;
    }

    /* ---------- check for a new release ---------- */

    public static function latest(bool $force = false): array
    {
        $repo = self::repo();
        if ($repo === '') {
            return ['ok' => false, 'error' => 'No update source is set. Enter the GitHub repository (owner/name) below.', 'newer' => false, 'checked' => time()];
        }
        $cache = self::cached();
        if (!$force && $cache && ($cache['repo'] ?? '') === $repo && time() - (int)($cache['checked'] ?? 0) < self::CACHE_SECONDS) {
            return $cache;
        }

        $res = self::http('https://api.github.com/repos/' . $repo . '/releases/latest');
        $out = ['ok' => false, 'error' => '', 'newer' => false, 'checked' => time(), 'repo' => $repo];
        if (!$res['ok']) {
            $out['error'] = $res['code'] === 404
                ? 'No release found in ' . $repo . '. Has a version been published yet, and is the repository public?'
                : 'Could not reach GitHub: ' . $res['error'];
            self::store($out);
            return $out;
        }
        $rel = json_decode($res['body'], true);
        $tag = is_array($rel) ? ltrim((string)($rel['tag_name'] ?? ''), 'vV') : '';
        if ($tag === '' || !preg_match('/^\d+\.\d+\.\d+/', $tag)) {
            $out['error'] = 'The latest release has no version number like v1.2.3.';
            self::store($out);
            return $out;
        }
        $assets = [];
        foreach ((array)($rel['assets'] ?? []) as $a) {
            $assets[(string)($a['name'] ?? '')] = (string)($a['browser_download_url'] ?? '');
        }
        $zip = $assets['rider-page-' . $tag . '.zip'] ?? '';
        $sha = $assets['rider-page-' . $tag . '.zip.sha256'] ?? '';
        $manifest = [];
        if (!empty($assets['manifest.json'])) {
            $mr = self::http($assets['manifest.json']);
            $manifest = $mr['ok'] ? (json_decode($mr['body'], true) ?: []) : [];
        }
        $out = [
            'ok' => true, 'error' => '', 'repo' => $repo, 'checked' => time(),
            'version' => $tag, 'newer' => version_compare($tag, self::version(), '>'),
            'zip' => $zip, 'sha' => $sha, 'page' => (string)($rel['html_url'] ?? ''), 'notes' => (string)($rel['body'] ?? ''),
            'manifest' => $manifest + ['min_php' => '8.1', 'breaking' => false, 'changelog' => (string)($rel['body'] ?? ''), 'warnings' => []],
        ];
        if ($zip === '' || $sha === '') {
            $out['ok'] = false;
            $out['error'] = 'The release is missing its zip or checksum file, so it cannot be installed safely.';
        }
        self::store($out);
        return $out;
    }

    private static function store(array $data): void
    {
        Settings::set('update_cache', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Checks that the server can do the update. Blocking ones stop the update. */
    public static function preflight(?array $manifest): array
    {
        $minPhp = (string)($manifest['min_php'] ?? '8.1');
        $checks = [];
        $checks[] = ['label' => 'PHP ' . $minPhp . ' or newer', 'ok' => version_compare(PHP_VERSION, $minPhp, '>='), 'detail' => 'This server runs PHP ' . PHP_VERSION];
        $checks[] = ['label' => 'Zip support', 'ok' => class_exists('ZipArchive'), 'detail' => class_exists('ZipArchive') ? 'Available' : 'The PHP zip extension is missing'];
        $net = function_exists('curl_init') || (bool)ini_get('allow_url_fopen');
        $checks[] = ['label' => 'Can download from GitHub', 'ok' => $net, 'detail' => $net ? 'Available' : 'Neither cURL nor allow_url_fopen is enabled'];
        foreach (['app', 'themes', 'assets'] as $d) {
            $checks[] = ['label' => 'Folder ' . $d . '/ is writable', 'ok' => is_writable(RP_ROOT . '/' . $d) && is_writable(RP_ROOT), 'detail' => is_writable(RP_ROOT . '/' . $d) ? 'Writable' : 'Not writable by PHP'];
        }
        $checks[] = ['label' => 'Folder storage/ is writable', 'ok' => is_writable(RP_STORAGE), 'detail' => is_writable(RP_STORAGE) ? 'Writable' : 'Not writable by PHP'];
        $free = @disk_free_space(RP_ROOT);
        $checks[] = ['label' => 'At least 100 MB free disk space', 'ok' => $free === false || $free > 100 * 1024 * 1024, 'detail' => $free === false ? 'Unknown' : round($free / 1048576) . ' MB free'];
        return $checks;
    }

    /* ---------- run the update ---------- */

    /** @return array{ok:bool,error:string} */
    public static function run(bool $confirmBreaking): array
    {
        @set_time_limit(300);
        $tmp = RP_STORAGE . '/tmp';
        $from = self::version();
        try {
            $latest = self::latest(true);
            if (!$latest['ok']) {
                return ['ok' => false, 'error' => $latest['error']];
            }
            if (!$latest['newer']) {
                return ['ok' => false, 'error' => 'You already have the latest version.'];
            }
            $m = $latest['manifest'];
            if (!empty($m['breaking']) && !$confirmBreaking) {
                return ['ok' => false, 'error' => 'This update contains changes that may break customisations. Tick the confirmation box to continue.'];
            }
            foreach (self::preflight($m) as $c) {
                if (!$c['ok']) {
                    return ['ok' => false, 'error' => 'The update cannot run: ' . $c['label'] . ' (' . $c['detail'] . '). You can also update by hand, see the README.'];
                }
            }

            self::rrmdir($tmp);
            mkdir($tmp . '/new', 0755, true);

            $zipFile = $tmp . '/update.zip';
            $dl = self::http($latest['zip'], $zipFile, 120);
            if (!$dl['ok']) {
                throw new RuntimeException('Download failed: ' . $dl['error']);
            }
            $shaRes = self::http($latest['sha']);
            if (!$shaRes['ok'] || !preg_match('/\b([a-f0-9]{64})\b/i', $shaRes['body'], $mm)) {
                throw new RuntimeException('The checksum file could not be read.');
            }
            if (!hash_equals(strtolower($mm[1]), hash_file('sha256', $zipFile))) {
                throw new RuntimeException('The downloaded file does not match its checksum. Nothing was changed.');
            }

            $backup = self::backup();
            self::extract($zipFile, $tmp . '/new');

            $newManifest = json_decode((string)@file_get_contents($tmp . '/new/manifest.json'), true);
            if (!is_file($tmp . '/new/app/bootstrap.php') || !is_array($newManifest) || ($newManifest['version'] ?? '') !== $latest['version']) {
                throw new RuntimeException('The downloaded package is not a valid Rider Page release.');
            }

            self::swap($tmp . '/new');
            file_put_contents(RP_STORAGE . '/update-pending.json', json_encode([
                'from' => $from, 'to' => $latest['version'], 'backup' => $backup, 'started' => time(),
            ]));
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            @unlink(RP_STORAGE . '/update-pending.json');
            self::log('Update failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Runs in the request after the swap, with the new code loaded. */
    public static function finish(): array
    {
        $pf = RP_STORAGE . '/update-pending.json';
        if (!is_file($pf)) {
            return ['ok' => true, 'message' => 'Nothing to finish.'];
        }
        $pending = json_decode((string)file_get_contents($pf), true) ?: [];
        try {
            $applied = Migrator::run();
            @unlink($pf);
            $r = Site::response('/');
            if ($r['status'] >= 500) {
                throw new RuntimeException('The start page did not load after the update.');
            }
            Settings::set('update_cache', '');
            self::rrmdir(RP_STORAGE . '/tmp');
            $n = count($applied);
            return ['ok' => true, 'message' => 'Updated to version ' . self::version() . '.' . ($n ? ' ' . $n . ' database change' . ($n > 1 ? 's' : '') . ' applied.' : '')];
        } catch (Throwable $e) {
            self::log('Update check failed: ' . $e->getMessage());
            @unlink($pf);
            $msg = self::rollback($pending);
            return ['ok' => false, 'message' => 'The update was cancelled because something went wrong (' . $e->getMessage() . '). ' . $msg];
        }
    }

    private static function rollback(array $pending): string
    {
        $backup = (string)($pending['backup'] ?? '');
        try {
            if (!is_file($backup)) {
                return 'No backup was found, please restore the files by hand.';
            }
            $tmp = RP_STORAGE . '/tmp/restore';
            self::rrmdir($tmp);
            mkdir($tmp, 0755, true);
            self::extract($backup, $tmp);
            self::swap($tmp);
            $dbBackup = preg_replace('/^code-/', 'db-', basename($backup, '.zip')) . '.sqlite';
            $dbFile = dirname($backup) . '/' . $dbBackup;
            $cfg = rp_config();
            if (($cfg['driver'] ?? '') === 'sqlite' && is_file($dbFile)) {
                copy($dbFile, (string)$cfg['path']);
                return 'Your previous version and data were restored.';
            }
            return 'Your previous version was restored. The database was not changed back automatically; a copy from before the update is in storage/backups.';
        } catch (Throwable $e) {
            self::log('Rollback failed: ' . $e->getMessage());
            return 'The automatic restore failed. The backup is in storage/backups, see the README for how to restore it.';
        } finally {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
        }
    }

    /* ---------- pieces ---------- */

    public static function backups(): array
    {
        $out = [];
        foreach (glob(RP_STORAGE . '/backups/*') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int)filesize($f), 'time' => (int)filemtime($f)];
        }
        usort($out, static fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /** @return string path of the code backup zip */
    private static function backup(): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP zip extension is needed for updates.');
        }
        $dir = RP_STORAGE . '/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $stamp = self::version() . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $zipFile = $dir . '/code-' . $stamp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup file.');
        }
        foreach (self::CODE_DIRS as $d) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(RP_ROOT . '/' . $d, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $zip->addFile($f->getPathname(), $d . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen(RP_ROOT . '/' . $d) + 1)));
                }
            }
        }
        foreach (self::CODE_FILES as $f) {
            if (is_file(RP_ROOT . '/' . $f)) {
                $zip->addFile(RP_ROOT . '/' . $f, $f);
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('Could not write the backup file.');
        }

        $cfg = rp_config();
        if (($cfg['driver'] ?? '') === 'sqlite') {
            copy((string)$cfg['path'], $dir . '/db-' . $stamp . '.sqlite');
        } else {
            $dump = [];
            foreach (Db::tables() as $t) {
                $dump[$t] = Db::rows('SELECT * FROM ' . $t);
            }
            file_put_contents($dir . '/db-' . $stamp . '.json', json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        }
        foreach (['code-*.zip', 'db-*'] as $pattern) {
            $files = glob($dir . '/' . $pattern) ?: [];
            usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($files, 3) as $old) {
                @unlink($old);
            }
        }
        return $zipFile;
    }

    private static function extract(string $zipFile, string $dest): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException('The package could not be opened.');
        }
        $allowed = ['app', 'themes', 'assets', 'index.php', 'manifest.json'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, ':')) {
                throw new RuntimeException('The package contains an unsafe path.');
            }
            $top = explode('/', $name)[0];
            if (!in_array($top, $allowed, true)) {
                continue;
            }
            $target = $dest . '/' . $name;
            if (str_ends_with($name, '/')) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                continue;
            }
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            $in = $zip->getStream($zip->getNameIndex($i));
            $out = fopen($target, 'wb');
            if (!$in || !$out) {
                throw new RuntimeException('Could not unpack ' . $name);
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
        $zip->close();
    }

    /** Replace the code folders with the ones in $new. Restores the old ones if anything fails. */
    private static function swap(string $new): void
    {
        $old = RP_STORAGE . '/tmp/old-' . bin2hex(random_bytes(3));
        mkdir($old, 0755, true);
        $moved = [];
        $copied = [];
        try {
            foreach (self::CODE_DIRS as $d) {
                if (!is_dir("$new/$d")) {
                    continue;
                }
                if (!rename(RP_ROOT . "/$d", "$old/$d")) {
                    throw new RuntimeException("Could not move the $d folder. Check the folder permissions.");
                }
                $moved[] = $d;
                if (!rename("$new/$d", RP_ROOT . "/$d")) {
                    throw new RuntimeException("Could not put the new $d folder in place.");
                }
            }
            foreach (self::CODE_FILES as $f) {
                if (!is_file("$new/$f")) {
                    continue;
                }
                if (is_file(RP_ROOT . "/$f")) {
                    copy(RP_ROOT . "/$f", "$old/$f");
                }
                $copied[] = $f;
                if (!copy("$new/$f", RP_ROOT . "/$f.new") || !rename(RP_ROOT . "/$f.new", RP_ROOT . "/$f")) {
                    throw new RuntimeException("Could not replace $f.");
                }
            }
        } catch (Throwable $e) {
            foreach (array_reverse($moved) as $d) {
                if (is_dir(RP_ROOT . "/$d")) {
                    @rename(RP_ROOT . "/$d", "$new/$d.failed");
                }
                @rename("$old/$d", RP_ROOT . "/$d");
            }
            foreach ($copied as $f) {
                if (is_file("$old/$f")) {
                    @copy("$old/$f", RP_ROOT . "/$f");
                }
            }
            throw $e;
        }
    }

    /** @return array{ok:bool,code:int,body:string,error:string} */
    private static function http(string $url, ?string $saveTo = null, int $timeout = 30): array
    {
        if (!str_starts_with($url, 'https://')) {
            return ['ok' => false, 'code' => 0, 'body' => '', 'error' => 'Only https:// addresses are allowed.'];
        }
        $ua = 'RiderPage/' . self::version();
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $fp = null;
            $opts = [
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json, application/octet-stream, */*'],
            ];
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                $opts[CURLOPT_PROTOCOLS_STR] = 'https';
                $opts[CURLOPT_REDIR_PROTOCOLS_STR] = 'https';
            } else {
                $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
                $opts[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            if ($saveTo !== null) {
                $fp = fopen($saveTo, 'wb');
                $opts[CURLOPT_FILE] = $fp;
            } else {
                $opts[CURLOPT_RETURNTRANSFER] = true;
            }
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($fp) {
                fclose($fp);
            }
            $ok = $body !== false && $code >= 200 && $code < 300;
            return ['ok' => $ok, 'code' => $code, 'body' => is_string($body) ? $body : '', 'error' => $ok ? '' : ($err ?: 'HTTP ' . $code)];
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['header' => "User-Agent: $ua\r\nAccept: */*\r\n", 'timeout' => $timeout, 'follow_location' => 1, 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $ctx);
            $code = 0;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                    $code = (int)$m[1];
                }
            }
            $ok = $body !== false && $code >= 200 && $code < 300;
            if ($ok && $saveTo !== null) {
                file_put_contents($saveTo, $body);
                $body = '';
            }
            return ['ok' => $ok, 'code' => $code, 'body' => is_string($body) ? $body : '', 'error' => $ok ? '' : 'HTTP ' . $code];
        }
        return ['ok' => false, 'code' => 0, 'body' => '', 'error' => 'Neither cURL nor allow_url_fopen is available on this server.'];
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private static function log(string $msg): void
    {
        @mkdir(RP_STORAGE . '/logs', 0755, true);
        @file_put_contents(RP_STORAGE . '/logs/error.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
    }

    /** For tests: apply a package from a local zip, skipping the download. */
    public static function applyLocal(string $zipFile, string $version): array
    {
        try {
            $tmp = RP_STORAGE . '/tmp';
            self::rrmdir($tmp);
            mkdir($tmp . '/new', 0755, true);
            $backup = self::backup();
            self::extract($zipFile, $tmp . '/new');
            self::swap($tmp . '/new');
            file_put_contents(RP_STORAGE . '/update-pending.json', json_encode(['from' => self::version(), 'to' => $version, 'backup' => $backup, 'started' => time()]));
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
