<?php
declare(strict_types=1);

/** The public site: one page with sections, plus the blog. */
final class Site
{
    public const SECTIONS = ['results', 'sponsors', 'media', 'blog', 'faq', 'contact'];

    public static function dispatch(string $path, string $method): void
    {
        $r = self::response($path);
        http_response_code($r['status']);
        header('Content-Type: ' . $r['type']);
        foreach ($r['headers'] as $h) {
            header($h);
        }
        if (str_starts_with($r['type'], 'text/html')) {
            send_headers(false);
        }
        echo $r['body'];
    }

    /** @return array{status:int,type:string,body:string,headers:string[]} */
    public static function response(string $path): array
    {
        if (is_file(RP_STORAGE . '/maintenance.flag') || is_file(RP_STORAGE . '/update-pending.json')) {
            // Self-contained on purpose: the database may be half-way through an update.
            return self::page(503, '<!doctype html><html lang="' . e(Lang::code()) . '"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
                . '<title>' . e(t('maint.title')) . '</title>'
                . '<style nonce="' . e(rp_nonce()) . '">body{font:18px/1.5 system-ui,sans-serif;max-width:32rem;margin:18vh auto;padding:0 1rem}</style></head>'
                . '<body>'
                . '<h1>' . e(t('maint.title')) . '</h1><p>' . e(t('maint.text')) . '</p></body></html>', ['Retry-After: 120']);
        }

        if ($path !== '/robots.txt' && self::maintenanceOn() && !self::isAdmin()) {
            $title = trim((string)Settings::get('maintenance_title', '')) ?: t('maint.title');
            $text = trim((string)Settings::get('maintenance_text', '')) ?: t('maint.text');
            return self::page(503, self::layout([
                'title' => $title,
                'content' => '<div class="in page-msg"><h1>' . e($title) . '</h1><p>' . nl2br(e($text)) . '</p></div>',
                'noindex' => true,
                'maintenance' => true,
            ]), ['Retry-After: 3600', 'Cache-Control: no-store']);
        }

        if ($path === '/robots.txt') {
            return self::page(200, "User-agent: *\nAllow: /\nDisallow: /admin\n\nSitemap: " . site_origin() . "/sitemap.xml\n", [], 'text/plain; charset=utf-8');
        }
        if ($path === '/sitemap.xml') {
            return self::page(200, self::sitemap(), [], 'application/xml; charset=utf-8');
        }
        if ($path === '/') {
            return self::page(200, self::home());
        }
        if ($path === '/blog') {
            return self::page(200, self::blogIndex());
        }
        if (preg_match('#^/blog/([a-z0-9-]+)$#', $path, $m)) {
            $html = self::post($m[1]);
            if ($html !== null) {
                return self::page(200, $html);
            }
        }
        return self::page(404, self::layout([
            'title' => t('e404.title'),
            'content' => '<div class="in page-msg"><h1>' . e(t('e404.title')) . '</h1><p>' . e(t('e404.text'))
                . '</p><p><a class="btn" href="' . e(url('/')) . '">' . e(t('e404.home')) . '</a></p></div>',
            'noindex' => true,
        ]));
    }

    /* ---------- maintenance mode ---------- */

    public static function maintenanceOn(): bool
    {
        return Settings::get('maintenance', '') === '1';
    }

    /** True when the visitor is logged in to the admin area (only looked up if a session cookie exists). */
    public static function isAdmin(): bool
    {
        static $admin = null;
        if ($admin === null) {
            $admin = false;
            if (PHP_SAPI !== 'cli' && !empty($_COOKIE['rp_session'])) {
                rp_session();
                $admin = !empty($_SESSION['uid']);
                session_write_close();
            }
        }
        return $admin;
    }

    /* ---------- pages ---------- */

    private static function home(): string
    {
        $c = self::context();
        $sections = '';
        foreach ($c['sections'] as $i => $id) {
            $sections .= tpl('themes/views/sections/' . $id, $c + ['alt' => $i % 2 === 0]);
        }
        $content = tpl('themes/views/home', $c + ['sectionsHtml' => $sections]);
        $p = $c['p'];
        $desc = trim((string)Settings::get('meta_description', '')) ?: Markdown::excerpt((string)$p['bio'], 155);
        return self::layout([
            'title' => $p['name'] . ($p['tagline'] !== '' ? ' – ' . $p['tagline'] : ''),
            'description' => $desc,
            'canonical' => site_origin() . '/',
            'image' => $p['hero'] !== '' ? site_origin() . '/' . $p['hero'] : '',
            'jsonld' => self::personLd($c, $desc),
            'content' => $content,
            'isHome' => true,
        ], $c);
    }

    private static function blogIndex(): string
    {
        $c = self::context();
        $content = tpl('themes/views/blog', $c + ['posts' => self::posts()]);
        return self::layout([
            'title' => t('blog.title') . ' – ' . $c['p']['name'],
            'description' => t('blog.intro'),
            'canonical' => site_origin() . '/blog',
            'content' => $content,
        ], $c);
    }

    private static function post(string $slug): ?string
    {
        $row = Db::row("SELECT * FROM posts WHERE slug = ? AND status = 'published' AND published_at <= ?", [$slug, now()]);
        if (!$row) {
            return null;
        }
        $c = self::context();
        $words = preg_match_all('/\p{L}+/u', (string)$row['body']);
        $row['minutes'] = max(1, (int)ceil($words / 200));
        $row['html'] = Markdown::render((string)$row['body']);
        $desc = $row['summary'] !== '' ? $row['summary'] : Markdown::excerpt((string)$row['body'], 155);
        $image = $row['cover'] !== '' ? $row['cover'] : $c['p']['hero'];
        $ld = [
            '@context' => 'https://schema.org', '@type' => 'BlogPosting',
            'headline' => $row['title'], 'datePublished' => date('c', (int)strtotime((string)$row['published_at'])),
            'dateModified' => date('c', (int)strtotime((string)$row['updated_at'])),
            'author' => ['@type' => 'Person', 'name' => $c['p']['name']],
            'mainEntityOfPage' => site_origin() . '/blog/' . $row['slug'],
        ];
        if ($image !== '') {
            $ld['image'] = site_origin() . '/' . $image;
        }
        return self::layout([
            'title' => $row['title'] . ' – ' . $c['p']['name'],
            'description' => $desc,
            'canonical' => site_origin() . '/blog/' . $row['slug'],
            'image' => $image !== '' ? site_origin() . '/' . $image : '',
            'ogType' => 'article',
            'jsonld' => $ld,
            'content' => tpl('themes/views/post', $c + ['post' => $row]),
        ], $c);
    }

    private static function sitemap(): string
    {
        $o = site_origin();
        $urls = [[$o . '/', date('Y-m-d')]];
        $posts = self::posts();
        if ($posts) {
            $urls[] = [$o . '/blog', substr((string)$posts[0]['published_at'], 0, 10)];
        }
        foreach ($posts as $p) {
            $urls[] = [$o . '/blog/' . $p['slug'], substr((string)($p['updated_at'] ?? $p['published_at']), 0, 10)];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $mod]) {
            $xml .= '  <url><loc>' . e($loc) . '</loc><lastmod>' . e($mod) . "</lastmod></url>\n";
        }
        return $xml . "</urlset>\n";
    }

    /* ---------- data ---------- */

    public static function sectionOrder(): array
    {
        $saved = array_values(array_filter(explode(',', (string)Settings::get('sections_order', ''))));
        $order = array_values(array_intersect($saved, self::SECTIONS));
        foreach (self::SECTIONS as $id) {
            if (!in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        return $order;
    }

    public static function sectionsOff(): array
    {
        return array_values(array_intersect(explode(',', (string)Settings::get('sections_off', '')), self::SECTIONS));
    }

    /** Sections that are switched on and have something to show. */
    public static function visibleSections(): array
    {
        $off = self::sectionsOff();
        $out = [];
        foreach (self::sectionOrder() as $id) {
            if (!in_array($id, $off, true) && self::hasContent($id)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    public static function hasContent(string $id): bool
    {
        return match ($id) {
            'results' => (int)Db::val('SELECT COUNT(*) FROM stats') > 0,
            'sponsors' => (int)Db::val('SELECT COUNT(*) FROM sponsors') > 0,
            'media' => youtube_id((string)Settings::get('video_url', '')) !== null || (int)Db::val('SELECT COUNT(*) FROM media') > 0,
            'blog' => count(self::posts()) > 0,
            'faq' => (int)Db::val('SELECT COUNT(*) FROM faqs') > 0,
            'contact' => trim((string)Settings::get('email', '')) !== '',
            default => false,
        };
    }

    public static function posts(int $limit = 0): array
    {
        $sql = "SELECT id, slug, title, summary, cover, published_at, updated_at FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC, id DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        return Db::rows($sql, [now()]);
    }

    private static function context(): array
    {
        $g = static fn(string $k, string $d = ''): string => (string)Settings::get($k, $d);
        $p = [
            'name' => $g('name', 'Your Name'),
            'tagline' => $g('tagline'),
            'bio' => $g('bio'),
            'hero' => $g('hero_image'),
            'sticker' => $g('sticker'),
            'email' => trim($g('email')),
            'video_id' => youtube_id($g('video_url')),
            'video_caption' => $g('video_caption'),
            'contact_title' => $g('contact_title') ?: t('contact.title'),
            'contact_text' => $g('contact_text') ?: t('contact.text'),
        ];
        $sections = self::visibleSections();
        $tiers = ['title' => [], 'partner' => [], 'supporter' => []];
        foreach (Db::rows('SELECT * FROM sponsors ORDER BY pos, id') as $s) {
            $tiers[isset($tiers[$s['tier']]) ? $s['tier'] : 'partner'][] = $s;
        }
        return [
            'p' => $p,
            'sections' => $sections,
            'design' => Presets::layout(),
            'socials' => Db::rows('SELECT * FROM socials ORDER BY pos, id'),
            'stats' => Db::rows('SELECT * FROM stats ORDER BY pos, id'),
            'tiers' => $tiers,
            'gallery' => Db::rows('SELECT * FROM media ORDER BY pos, id'),
            'faqs' => Db::rows('SELECT * FROM faqs ORDER BY pos, id'),
            'latest' => self::posts(3),
            'hasBlog' => in_array('blog', $sections, true) || count(self::posts(1)) > 0,
            'nav' => array_values(array_filter($sections, static fn($id) => $id !== 'contact')),
            'hasContact' => in_array('contact', $sections, true),
        ];
    }

    private static function personLd(array $c, string $desc): array
    {
        $p = $c['p'];
        $ld = ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $p['name'], 'url' => site_origin() . '/'];
        if ($desc !== '') {
            $ld['description'] = $desc;
        }
        if ($p['hero'] !== '') {
            $ld['image'] = site_origin() . '/' . $p['hero'];
        }
        $same = array_values(array_filter(array_column($c['socials'], 'url')));
        if ($same) {
            $ld['sameAs'] = $same;
        }
        $graph = [$ld];
        if ($c['faqs'] && in_array('faq', $c['sections'], true)) {
            $graph[] = [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn($f) => [
                    '@type' => 'Question', 'name' => $f['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
                ], $c['faqs']),
            ];
            unset($graph[0]['@context']);
            return ['@context' => 'https://schema.org', '@graph' => $graph];
        }
        return $ld;
    }

    /* ---------- output ---------- */

    private static function layout(array $v, ?array $c = null): string
    {
        $c ??= self::context();
        $v += [
            'description' => '', 'canonical' => '', 'image' => '', 'ogType' => 'website',
            'jsonld' => null, 'noindex' => false, 'isHome' => false, 'maintenance' => false,
        ];
        $v['adminBanner'] = self::maintenanceOn() && self::isAdmin();
        return tpl('themes/views/layout', $v + $c);
    }

    private static function page(int $status, string $body, array $headers = [], string $type = 'text/html; charset=utf-8'): array
    {
        return ['status' => $status, 'type' => $type, 'body' => $body, 'headers' => $headers];
    }
}
