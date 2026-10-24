<?php
declare(strict_types=1);

/**
 * Visit statistics that stay on the owner's server. No cookie is set, no IP address is stored,
 * and visitors who send "Do Not Track" or "Global Privacy Control" are not counted.
 */
final class Analytics
{
    private const BOT = '/bot|crawl|spider|slurp|preview|fetch|monitor|headless|lighthouse|pingdom|uptime|curl|wget|python|go-http|okhttp|libwww|httpclient|facebookexternalhit|embedly|whatsapp|telegram|discord|slack|validator/i';
    private const CLICKS_PER_DAY = 200;

    /** Off for sites that existed before this feature, so nothing starts being collected unannounced. */
    public static function enabled(): bool
    {
        return Settings::get('stats_on', '0') === '1';
    }

    public static function retentionDays(): int
    {
        $d = (int)Settings::get('stats_days', '90');
        return in_array($d, [30, 90, 365], true) ? $d : 90;
    }

    /** True when this request must not be counted. */
    public static function skip(): bool
    {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || preg_match(self::BOT, $ua)) {
            return true;
        }
        if (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') {
            return true;
        }
        $purpose = strtolower(($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_X_MOZ'] ?? ''));
        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
            return true;
        }
        return Site::isAdmin();
    }

    /** One visitor per day: a keyed hash of IP address and browser that is different every day. */
    private static function visitor(): string
    {
        $secret = (string)(rp_config()['secret'] ?? 'rider-page');
        $salt = hash_hmac('sha256', date('Y-m-d'), $secret);
        return substr(hash_hmac('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), $salt), 0, 16);
    }

    private static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua) || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile'))) {
            return 'tablet';
        }
        return preg_match('/Mobi|iPhone|iPod|Android/i', $ua) ? 'phone' : 'desktop';
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/'), str_contains($ua, 'EdgA/'), str_contains($ua, 'EdgiOS/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS/') => 'Firefox',
            str_contains($ua, 'Chrome/'), str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Other',
        };
    }

    /** Domain of the page the visitor came from, without "www."; empty for direct visits and our own pages. */
    private static function referrer(): string
    {
        $host = strtolower((string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
        $host = (string)preg_replace('/^www\./', '', $host);
        $own = (string)preg_replace('/^www\./', '', strtolower((string)parse_url(site_origin(), PHP_URL_HOST)));
        return ($host === '' || $host === $own) ? '' : mb_substr($host, 0, 120);
    }

    /** Count a page view. Never breaks the page if anything goes wrong. */
    public static function page(string $path): void
    {
        if (!self::enabled() || self::skip()) {
            return;
        }
        try {
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            Db::insert('hits', [
                'day' => date('Y-m-d'), 'ts' => time(), 'kind' => 'page', 'vh' => self::visitor(),
                'path' => mb_substr($path, 0, 190), 'ref' => self::referrer(), 'dev' => self::device($ua), 'br' => self::browser($ua),
            ]);
            if (random_int(1, 100) === 1) {
                self::prune();
            }
        } catch (Throwable) {
        }
    }

    /** Count a click on a sponsor, social or video link. Only targets that really exist on the site are accepted. */
    public static function click(string $target, string $path): void
    {
        if (!self::enabled() || self::skip() || !preg_match('/^(sponsor|social|video):(.{1,80})$/su', $target, $m)) {
            return;
        }
        try {
            $ok = match ($m[1]) {
                'sponsor' => Db::val('SELECT COUNT(*) FROM sponsors WHERE name = ?', [$m[2]]) > 0,
                'social' => isset(social_types()[$m[2]]) && Db::val('SELECT COUNT(*) FROM socials WHERE type = ?', [$m[2]]) > 0,
                default => $m[2] === 'play',
            };
            $vh = self::visitor();
            if (!$ok || (int)Db::val("SELECT COUNT(*) FROM hits WHERE kind = 'click' AND day = ? AND vh = ?", [date('Y-m-d'), $vh]) >= self::CLICKS_PER_DAY) {
                return;
            }
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            Db::insert('hits', [
                'day' => date('Y-m-d'), 'ts' => time(), 'kind' => 'click', 'vh' => $vh,
                'path' => preg_match('~^/[A-Za-z0-9_/.-]{0,180}$~', $path) ? $path : '/',
                'dev' => self::device($ua), 'br' => self::browser($ua), 'target' => mb_substr($target, 0, 100),
            ]);
        } catch (Throwable) {
        }
    }

    /** Fold old detail rows into daily totals and delete them. */
    public static function prune(): void
    {
        $cut = date('Y-m-d', strtotime('-' . self::retentionDays() . ' days'));
        foreach (Db::rows("SELECT day, COUNT(*) AS v, COUNT(DISTINCT vh) AS u FROM hits WHERE kind = 'page' AND day < ? GROUP BY day", [$cut]) as $d) {
            Db::delete('hit_days', 'day = ?', [$d['day']]);
            Db::insert('hit_days', ['day' => $d['day'], 'views' => (int)$d['v'], 'visitors' => (int)$d['u']]);
        }
        Db::delete('hits', 'day < ?', [$cut]);
    }

    public static function wipe(): void
    {
        Db::run('DELETE FROM hits');
        Db::run('DELETE FROM hit_days');
    }

    /** @return array everything the Statistics page shows for the last $days days */
    public static function report(int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $by = [];
        foreach (Db::rows("SELECT day, COUNT(*) AS v, COUNT(DISTINCT vh) AS u FROM hits WHERE kind = 'page' AND day >= ? GROUP BY day", [$since]) as $r) {
            $by[$r['day']] = [(int)$r['v'], (int)$r['u']];
        }
        foreach (Db::rows('SELECT day, views, visitors FROM hit_days WHERE day >= ?', [$since]) as $r) {
            $by[$r['day']] ??= [(int)$r['views'], (int)$r['visitors']];
        }
        $series = [];
        $views = $visitors = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            [$v, $u] = $by[$day] ?? [0, 0];
            $series[] = ['day' => $day, 'views' => $v, 'visitors' => $u];
            $views += $v;
            $visitors += $u;
        }
        $group = static fn(string $col): array => Db::rows(
            "SELECT $col AS k, COUNT(*) AS n FROM hits WHERE kind = 'page' AND day >= ? GROUP BY $col ORDER BY n DESC, k LIMIT 10",
            [$since]
        );
        return [
            'days' => $days, 'since' => $since, 'series' => $series, 'views' => $views, 'visitors' => $visitors,
            'pages' => Db::rows("SELECT path AS k, COUNT(*) AS n, COUNT(DISTINCT vh) AS u FROM hits WHERE kind = 'page' AND day >= ? GROUP BY path ORDER BY n DESC, k LIMIT 10", [$since]),
            'referrers' => $group('ref'),
            'devices' => $group('dev'),
            'browsers' => $group('br'),
            'clicks' => Db::rows("SELECT target AS k, COUNT(*) AS n FROM hits WHERE kind = 'click' AND day >= ? GROUP BY target ORDER BY n DESC, k LIMIT 15", [$since]),
            'rows' => (int)Db::val('SELECT COUNT(*) FROM hits'),
        ];
    }

    /** @return array<int,array> rows for a CSV download */
    public static function csv(string $what, int $days): array
    {
        $r = self::report($days);
        return match ($what) {
            'pages' => array_map(static fn($x) => [$x['k'], $x['n'], $x['u']], $r['pages']),
            'clicks' => array_map(static fn($x) => [$x['k'], $x['n']], $r['clicks']),
            default => array_map(static fn($x) => [$x['day'], $x['views'], $x['visitors']], $r['series']),
        };
    }
}
