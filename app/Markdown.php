<?php
declare(strict_types=1);

/**
 * A small, safe Markdown subset for blog posts. Raw HTML is never passed through.
 *
 * Supported: # headings, paragraphs, **bold**, *italic*, `code`, [links](https://...),
 * ![images](/uploads/x.jpg), > quotes, - and 1. lists, --- rules, ``` code blocks,
 * and a YouTube link on a line of its own (shown as a click-to-load video).
 */
final class Markdown
{
    public static function render(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $html = [];
        $para = [];
        $i = 0;
        $n = count($lines);

        $flush = static function () use (&$para, &$html): void {
            if ($para) {
                $html[] = '<p>' . self::inline(implode("\n", $para)) . '</p>';
                $para = [];
            }
        };

        while ($i < $n) {
            $line = $lines[$i];
            $trim = trim($line);

            if (str_starts_with($trim, '```')) {
                $flush();
                $code = [];
                $i++;
                while ($i < $n && !str_starts_with(trim($lines[$i]), '```')) {
                    $code[] = $lines[$i++];
                }
                $i++;
                $html[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
                continue;
            }
            if ($trim === '') {
                $flush();
                $i++;
                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.+)$/', $trim, $m)) {
                $flush();
                $level = strlen($m[1]) + 1; // the post title is the h1
                $html[] = "<h$level>" . self::inline($m[2]) . "</h$level>";
                $i++;
                continue;
            }
            if (preg_match('/^(-{3,}|\*{3,})$/', $trim)) {
                $flush();
                $html[] = '<hr>';
                $i++;
                continue;
            }
            if (str_starts_with($trim, '>')) {
                $flush();
                $q = [];
                while ($i < $n && str_starts_with(trim($lines[$i]), '>')) {
                    $q[] = ltrim(substr(trim($lines[$i]), 1));
                    $i++;
                }
                $html[] = '<blockquote>' . self::inline(implode("\n", $q)) . '</blockquote>';
                continue;
            }
            if (preg_match('/^[-*]\s+/', $trim) || preg_match('/^\d+\.\s+/', $trim)) {
                $flush();
                $ordered = (bool)preg_match('/^\d+\.\s+/', $trim);
                $items = [];
                while ($i < $n && preg_match($ordered ? '/^\d+\.\s+(.*)$/' : '/^[-*]\s+(.*)$/', trim($lines[$i]), $m)) {
                    $items[] = '<li>' . self::inline($m[1]) . '</li>';
                    $i++;
                }
                $tag = $ordered ? 'ol' : 'ul';
                $html[] = "<$tag>" . implode('', $items) . "</$tag>";
                continue;
            }
            if (($id = youtube_id($trim)) !== null && preg_match('~^https?://\S+$~', $trim)) {
                $flush();
                $html[] = self::video($id);
                $i++;
                continue;
            }
            if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$/', $trim, $m) && ($src = self::imageUrl($m[2])) !== '') {
                $flush();
                $alt = e($m[1]);
                // An optional title holds the photo credit: "Name" or "Name | https://link"
                $credit = '';
                if (!empty($m[3])) {
                    $parts = explode('|', $m[3], 2);
                    $credit = credit_html($parts[0], $parts[1] ?? '');
                }
                $cap = $alt . ($alt !== '' && $credit !== '' ? ' &middot; ' : '') . $credit;
                $html[] = '<figure><img src="' . e($src) . '" alt="' . $alt . '" loading="lazy">'
                    . ($cap !== '' ? '<figcaption>' . $cap . '</figcaption>' : '') . '</figure>';
                $i++;
                continue;
            }
            $para[] = $trim;
            $i++;
        }
        $flush();
        return implode("\n", $html);
    }

    public static function video(string $id): string
    {
        return '<div class="video" data-yt="' . e($id) . '"><div class="vframe">'
            . '<button type="button" class="play" aria-label="' . e(t('video.play')) . '"></button>'
            . '<span class="vnote">' . e(t('video.note')) . '</span></div>'
            . '<noscript><a href="https://www.youtube.com/watch?v=' . e($id) . '" rel="noopener">' . e(t('video.watch')) . '</a></noscript></div>';
    }

    public static function excerpt(string $text, int $len = 160): string
    {
        $plain = preg_replace('/[#>*_`!\[\]\(\)-]+/', ' ', $text) ?? $text;
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? '');
        return mb_strlen($plain) > $len ? rtrim(mb_substr($plain, 0, $len - 1)) . '…' : $plain;
    }

    private static function imageUrl(string $u): string
    {
        $u = html_entity_decode($u, ENT_QUOTES);
        if (str_starts_with($u, 'uploads/')) {
            return url($u);
        }
        if (str_starts_with($u, '/') && !str_starts_with($u, '//') && !str_contains($u, '..')) {
            return $u;
        }
        // Images from other sites are not allowed: they would leak visitors' IP addresses and
        // the page's content security policy blocks them anyway. Upload the picture instead.
        return '';
    }

    private static function inline(string $s): string
    {
        $s = e($s);
        $codes = [];
        $s = preg_replace_callback('/`([^`]+)`/', static function ($m) use (&$codes) {
            $codes[] = '<code>' . $m[1] . '</code>';
            return "\x00" . (count($codes) - 1) . "\x00";
        }, $s) ?? $s;

        $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', static function ($m) {
            $src = self::imageUrl($m[2]);
            return $src === '' ? $m[0] : '<img src="' . e($src) . '" alt="' . $m[1] . '" loading="lazy">';
        }, $s) ?? $s;

        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function ($m) {
            $href = html_entity_decode($m[2], ENT_QUOTES);
            if (str_starts_with($href, '#') || (str_starts_with($href, '/') && !str_starts_with($href, '//'))) {
                return '<a href="' . e($href) . '">' . $m[1] . '</a>';
            }
            if (str_starts_with($href, 'mailto:')) {
                return '<a href="' . e($href) . '">' . $m[1] . '</a>';
            }
            $safe = safe_url($href);
            return $safe === ''
                ? $m[1]
                : '<a href="' . e($safe) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $s) ?? $s;

        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s) ?? $s;
        $s = preg_replace_callback('/\x00(\d+)\x00/', static fn($m) => $codes[(int)$m[1]], $s) ?? $s;
        return str_replace("\n", '<br>', $s);
    }
}
