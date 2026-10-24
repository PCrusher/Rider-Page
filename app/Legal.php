<?php
declare(strict_types=1);

/**
 * Legal pages: which pages a site owner needs in which part of the world, and draft texts
 * for them that are filled with the owner's details. The templates are a starting point,
 * not legal advice.
 */
final class Legal
{
    /** Address words that pages may not use because the site itself needs them. */
    private const RESERVED = [
        'admin', 'assets', 'uploads', 'blog', 'contact', 'install', 'storage', 'app', 'themes',
        'sitemap', 'robots', 'index', 'api', 'login', 'logout',
    ];

    /** Page role -> language -> [address, title] */
    private const PAGES = [
        'imprint' => ['de' => ['impressum', 'Impressum'], 'en' => ['legal-notice', 'Legal notice']],
        'privacy' => ['de' => ['datenschutz', 'Datenschutzerklärung'], 'en' => ['privacy', 'Privacy policy']],
        'disclosure' => ['de' => ['werbung', 'Werbung und Partnerschaften'], 'en' => ['sponsorships', 'Sponsorships and advertising']],
    ];

    public static function regions(): array
    {
        $disclosure = ['Sponsorships and advertising', 'recommended',
            'Say clearly which partners support you and that paid content is marked as advertising. It builds trust, and in many countries hidden advertising is illegal.'];
        return [
            'dach' => [
                'name' => 'Germany, Austria, Switzerland',
                'summary' => 'The strictest rules for a personal website. A legal notice (Impressum) and a privacy policy are expected on every site that earns anything.',
                'kinds' => ['imprint-dach', 'privacy-gdpr', 'disclosure'],
                'needs' => [
                    ['Legal notice (Impressum)', 'required', 'Required for websites that are run as a business, and sponsors, ads or affiliate links make a site commercial. Legal basis: § 5 DDG (Germany), § 5 ECG (Austria), Art. 3 UWG (Switzerland). It needs your real name, a postal address you can be served at, an email address and a second quick way to reach you, such as a phone number or a contact form. It must be reachable from every page, for example from the footer.'],
                    ['Privacy policy', 'required', 'Required as soon as the site processes personal data. Server logs and the contact form already do. It has to say what is processed, why, on what legal basis, for how long, and what rights people have (GDPR, in Switzerland the revised data protection act).'],
                    array_replace($disclosure, [2 => 'In Germany, paid or sponsored content must be clearly marked as advertising ("Werbung" or "Anzeige"). Mark sponsored posts at their start and keep this page as a general explanation.']),
                ],
                'notes' => [
                    'No cookie banner is needed while the site uses no tracking cookies and loads nothing from third parties before a click. Rider Page is built that way.',
                    'Ask your hosting provider for a data processing agreement (Auftragsverarbeitungsvertrag). Most providers offer one in the customer area.',
                    'If you use a mail service for the contact form, it also processes personal data and should be named in the privacy policy (field "Other services").',
                ],
            ],
            'eu' => [
                'name' => 'Another EU or EEA country',
                'summary' => 'GDPR applies. Most countries also require basic provider information on commercial websites.',
                'kinds' => ['imprint-eu', 'privacy-gdpr', 'disclosure'],
                'needs' => [
                    ['Legal notice', 'likely', 'The e-Commerce Directive (Art. 5) requires commercial websites to show who runs them: name, address, email, and company register and VAT number if you have them. Your national law may add more, so check it.'],
                    ['Privacy policy', 'required', 'Required under the GDPR as soon as personal data is processed (server logs, contact form).'],
                    array_replace($disclosure, [2 => 'Consumer protection law (Unfair Commercial Practices Directive) bans hidden advertising. Mark sponsored content as advertising.']),
                ],
                'notes' => [
                    'No cookie banner is needed while the site uses no tracking cookies and loads nothing from third parties before a click.',
                    'Sign a data processing agreement with your hosting provider if you have not.',
                ],
            ],
            'uk' => [
                'name' => 'United Kingdom',
                'summary' => 'UK GDPR and the Data Protection Act apply. Business websites must show who is behind them.',
                'kinds' => ['imprint-uk', 'privacy-gdpr', 'disclosure'],
                'needs' => [
                    ['Legal information', 'likely', 'The E-Commerce Regulations 2002 require online service providers to show their name, address and email. Companies must also show the company name, registration number and registered office. VAT-registered businesses show the VAT number.'],
                    ['Privacy notice', 'required', 'Required under UK GDPR when you process personal data (server logs, contact form).'],
                    array_replace($disclosure, [2 => 'UK advertising rules (ASA, CMA) require paid or gifted content to be clearly labelled, for example "Ad" or "Gifted".']),
                ],
                'notes' => [
                    'If you process personal data for a business, you may have to register with the Information Commissioner\'s Office and pay a small data protection fee. Check ico.org.uk.',
                    'No cookie banner is needed while the site uses no tracking cookies and loads nothing from third parties before a click.',
                ],
            ],
            'us' => [
                'name' => 'United States',
                'summary' => 'No single federal privacy law and no legal notice requirement, but a privacy policy is expected, and sponsorships must be disclosed.',
                'kinds' => ['privacy-us', 'disclosure'],
                'needs' => [
                    ['Privacy policy', 'likely', 'California (CalOPPA) requires a privacy policy for sites that collect personal data from California residents, which a contact form does. Many services and sponsors also ask for one.'],
                    array_replace($disclosure, [1 => 'required', 2 => 'The FTC Endorsement Guides require a clear disclosure of any material connection to a brand: payments, free products, affiliate links. Put the disclosure near the content, not only on a separate page.']),
                ],
                'notes' => [
                    'A legal notice (Impressum) is not required in the US.',
                    'If you want terms of use, add them as a normal page under Pages.',
                ],
            ],
            'other' => [
                'name' => 'Somewhere else',
                'summary' => 'Rules differ a lot. These pages cover the usual ground, but check what your own country requires.',
                'kinds' => ['imprint-eu', 'privacy-gdpr', 'disclosure'],
                'needs' => [
                    ['Legal notice', 'recommended', 'Many countries require commercial websites to show who runs them. It also builds trust with sponsors and visitors.'],
                    ['Privacy policy', 'likely', 'Most countries have a data protection law, and the GDPR applies to visitors from the EU. A short, honest policy is expected everywhere.'],
                    array_replace($disclosure, [2 => 'Sponsorships and paid content should be marked clearly. Many countries require it.']),
                ],
                'notes' => [
                    'Ask a local lawyer or your national data protection authority what applies to a personal website with sponsors.',
                ],
            ],
        ];
    }

    /** Page kinds a region needs. */
    public static function kinds(string $region): array
    {
        return self::regions()[$region]['kinds'] ?? [];
    }

    /** Languages for which every page of the region has a template. */
    public static function langs(string $region): array
    {
        $langs = [];
        foreach (['en', 'de'] as $lang) {
            $ok = self::kinds($region) !== [];
            foreach (self::kinds($region) as $kind) {
                $ok = $ok && is_file(self::file($kind, $lang));
            }
            if ($ok) {
                $langs[] = $lang;
            }
        }
        return $langs;
    }

    private static function file(string $kind, string $lang): string
    {
        return RP_APP . '/legal/' . $kind . '.' . $lang . '.md';
    }

    /** @return array{0:string,1:string} address and title of a page kind in a language */
    public static function pageInfo(string $kind, string $lang): array
    {
        $role = explode('-', $kind)[0];
        return self::PAGES[$role][$lang] ?? self::PAGES[$role]['en'];
    }

    /** The owner's details, with sensible defaults for the fields that sit inside sentences. */
    public static function vars(string $lang): array
    {
        $s = static fn(string $k): string => trim((string)Settings::get('legal_' . $k, ''));
        $de = $lang === 'de';
        $name = $s('name') ?: trim((string)Settings::get('name', ''));
        return [
            'name' => $name,
            'street' => $s('street'),
            'city' => $s('city'),
            'country' => $s('country'),
            'email' => $s('email') ?: trim((string)Settings::get('email', '')),
            'phone' => $s('phone'),
            'register' => $s('register'),
            'vat' => $s('vat'),
            'responsible' => $s('responsible') ?: $name,
            'hosting' => $s('hosting') ?: ($de ? 'meinem Hosting-Anbieter' : 'my hosting provider'),
            'retention' => $s('retention') ?: ($de ? '6 Monaten' : '6 months'),
            'other_services' => trim((string)Settings::get('legal_other_services', '')),
            'smtp_host' => trim((string)Settings::get('smtp_host', '')),
            'date' => $de ? date('d.m.Y') : date('j F Y'),
        ];
    }

    public static function feature(string $key): bool
    {
        $v = Settings::get('legal_f_' . $key, null);
        if ($v !== null) {
            return $v === '1';
        }
        return match ($key) {
            'contact_form' => trim((string)Settings::get('email', '')) !== '',
            'youtube' => youtube_id((string)Settings::get('video_url', '')) !== null
                || (int)Db::val("SELECT COUNT(*) FROM posts WHERE body LIKE '%youtu%'") > 0,
            'sponsors' => (int)Db::val('SELECT COUNT(*) FROM sponsors') > 0,
            default => false,
        };
    }

    public static function flags(string $region, array $vars): array
    {
        return [
            'contact_form' => self::feature('contact_form'),
            'youtube' => self::feature('youtube'),
            'sponsors' => self::feature('sponsors'),
            'commercial' => Settings::get('legal_type', 'commercial') !== 'private',
            'company' => Settings::get('legal_form', 'person') === 'company',
            'has_reg' => $vars['register'] !== '' || $vars['vat'] !== '',
            'other_services' => $vars['other_services'] !== '',
            'smtp' => $vars['smtp_host'] !== '',
            'dach' => $region === 'dach',
            'uk' => $region === 'uk',
            'us' => $region === 'us',
            'eu' => $region === 'eu',
            'other' => $region === 'other',
        ];
    }

    /**
     * Fill a template. {{key}} is replaced by the value; a line whose value is empty is left out.
     * {{#if flag}}...{{/if}} and {{#unless flag}}...{{/unless}} switch parts on and off (they may be nested).
     */
    public static function render(string $tpl, array $vars, array $flags): string
    {
        $pattern = '/\{\{#(if|unless) (\w+)\}\}((?:(?!\{\{#(?:if|unless) ).)*?)\{\{\/\1\}\}/s';
        do {
            $tpl = (string)preg_replace_callback($pattern, static function (array $m) use ($flags): string {
                $on = !empty($flags[$m[2]]);
                return ($m[1] === 'unless' ? !$on : $on) ? $m[3] : '';
            }, $tpl, -1, $count);
        } while ($count > 0);

        $out = [];
        foreach (explode("\n", $tpl) as $line) {
            if (preg_match_all('/\{\{(\w+)\}\}/', $line, $mm)) {
                foreach ($mm[1] as $key) {
                    if (trim((string)($vars[$key] ?? '')) === '') {
                        continue 2;
                    }
                }
                $line = (string)preg_replace_callback('/\{\{(\w+)\}\}/', static fn(array $m): string => (string)$vars[$m[1]], $line);
            }
            $out[] = $line;
        }
        $text = implode("\n", $out);
        $text = (string)preg_replace("/\n{3,}/", "\n\n", $text);
        $text = (string)preg_replace('/^(- .*)\n\n(?=- )/m', "$1\n", $text);
        return trim($text) . "\n";
    }

    /** @return ?array{slug:string,title:string,body:string} */
    public static function build(string $kind, string $region, string $lang): ?array
    {
        $file = self::file($kind, $lang);
        if (!is_file($file)) {
            return null;
        }
        $vars = self::vars($lang);
        [$slug, $title] = self::pageInfo($kind, $lang);
        return ['slug' => $slug, 'title' => $title, 'body' => self::render((string)file_get_contents($file), $vars, self::flags($region, $vars))];
    }

    /**
     * Create draft pages for a region. Pages the owner wrote themselves are never touched.
     * @return array{created:string[],updated:string[],skipped:string[]}
     */
    public static function create(string $region, string $lang, bool $replace): array
    {
        $res = ['created' => [], 'updated' => [], 'skipped' => []];
        foreach (self::kinds($region) as $kind) {
            $b = self::build($kind, $region, $lang);
            if ($b === null) {
                continue;
            }
            $existing = Db::row('SELECT * FROM pages WHERE slug = ?', [$b['slug']]);
            if ($existing) {
                if ($replace && (string)$existing['template'] !== '') {
                    Db::update('pages', ['title' => $b['title'], 'body' => $b['body'], 'template' => $kind, 'updated_at' => now()], 'id = ?', [(int)$existing['id']]);
                    $res['updated'][] = $b['slug'];
                } else {
                    $res['skipped'][] = $b['slug'];
                }
                continue;
            }
            Db::insert('pages', [
                'slug' => $b['slug'], 'title' => $b['title'], 'body' => $b['body'], 'status' => 'draft', 'in_footer' => 1,
                'pos' => (int)Db::val('SELECT COALESCE(MAX(pos), 0) FROM pages') + 1, 'template' => $kind, 'updated_at' => now(),
            ]);
            $res['created'][] = $b['slug'];
        }
        return $res;
    }

    /** @return ?string an error message when the address can not be used for a page */
    public static function slugError(string $slug, int $exceptId = 0): ?string
    {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 60) {
            return 'The address may only contain lower-case letters, numbers and hyphens.';
        }
        if (in_array($slug, self::RESERVED, true)) {
            return 'The address "' . $slug . '" is used by the system. Choose another one.';
        }
        if (Db::val('SELECT COUNT(*) FROM pages WHERE slug = ? AND id <> ?', [$slug, $exceptId]) > 0) {
            return 'Another page already uses the address "' . $slug . '".';
        }
        return null;
    }
}
