<?php
declare(strict_types=1);

/** Redirects from old addresses and the log of addresses that were not found. */
final class Redirects
{
    /** Folders and routes of the system itself; these can not be redirected. */
    public const RESERVED = ['/admin', '/assets', '/uploads', '/install', '/storage', '/app', '/themes'];

    public static function normalize(string $p): string
    {
        $p = trim($p);
        $p = preg_replace('/[?#].*$/', '', $p) ?? $p;
        if (preg_match('~^https?://[^/]+(/.*)?$~i', $p, $m)) {
            $p = $m[1] ?? '/';
        }
        if ($p === '') {
            return '';
        }
        return '/' . trim($p, '/');
    }

    private static function internal(string $to): bool
    {
        return str_starts_with($to, '/') && !str_starts_with($to, '//');
    }

    /**
     * Check a redirect before saving.
     * @return array{0:?array,1:?string} the row to store, or an error message
     */
    public static function prepare(string $from, string $to, int $code, int $id = 0): array
    {
        $from = self::normalize($from);
        if ($from === '' || $from === '/') {
            return [null, 'Enter the old address, for example /old-page'];
        }
        if (mb_strlen($from) > 190 || preg_match('/[\s\x00-\x1f]/', $from) || str_contains($from, '..')) {
            return [null, 'The old address contains characters that are not allowed, or is too long.'];
        }
        foreach (self::RESERVED as $r) {
            if ($from === $r || str_starts_with($from, $r . '/')) {
                return [null, 'Addresses starting with ' . $r . ' belong to the system and can not be redirected.'];
            }
        }

        $to = trim($to);
        if ($to === '') {
            return [null, 'Enter the new address, for example /blog/new-post or https://example.com'];
        }
        if (self::internal($to)) {
            if (mb_strlen($to) > 480 || preg_match('/[\s\x00-\x1f]/', $to) || str_contains($to, '..')) {
                return [null, 'The new address contains characters that are not allowed.'];
            }
        } else {
            $to = safe_url($to);
            if ($to === '') {
                return [null, 'The new address must start with / (a page on this site) or with https://'];
            }
        }
        if (self::internal($to) && self::normalize($to) === $from) {
            return [null, 'The old and the new address are the same.'];
        }
        if (Db::val('SELECT COUNT(*) FROM redirects WHERE from_path = ? AND id <> ?', [$from, $id]) > 0) {
            return [null, 'There is already a redirect for ' . $from . '. Edit that one instead.'];
        }
        if (self::internal($to)) {
            $cur = self::normalize($to);
            for ($i = 0; $i < 10; $i++) {
                $next = Db::row('SELECT to_url FROM redirects WHERE from_path = ? AND id <> ?', [$cur, $id]);
                if (!$next || !self::internal((string)$next['to_url'])) {
                    break;
                }
                $cur = self::normalize((string)$next['to_url']);
                if ($cur === $from) {
                    return [null, 'This would create a loop of redirects that point at each other.'];
                }
            }
        }
        return [['from_path' => $from, 'to_url' => $to, 'code' => $code === 302 ? 302 : 301], null];
    }

    public static function find(string $path): ?array
    {
        try {
            return Db::row('SELECT * FROM redirects WHERE from_path = ?', [$path]);
        } catch (Throwable) {
            return null; // table not created yet (update in progress)
        }
    }

    public static function target(string $to): string
    {
        return self::internal($to) ? url($to) : $to;
    }

    public static function hit(int $id): void
    {
        try {
            Db::run('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$id]);
        } catch (Throwable) {
        }
    }

    /** Remember an address that was not found (at most 500 are kept, oldest dropped first). */
    public static function log(string $path): void
    {
        if (mb_strlen($path) > 190 || preg_match('/[\x00-\x1f]/', $path)) {
            return;
        }
        try {
            $n = Db::run('UPDATE not_found SET hits = hits + 1, last_seen = ? WHERE path = ?', [now(), $path])->rowCount();
            if ($n === 0) {
                if ((int)Db::val('SELECT COUNT(*) FROM not_found') >= 500) {
                    Db::run('DELETE FROM not_found WHERE id IN (SELECT id FROM (SELECT id FROM not_found ORDER BY last_seen ASC LIMIT 100) AS old)');
                }
                Db::insert('not_found', ['path' => $path, 'hits' => 1, 'last_seen' => now()]);
            }
        } catch (Throwable) {
        }
    }
}
