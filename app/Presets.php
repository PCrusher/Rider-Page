<?php
declare(strict_types=1);

/** Colour presets. Each one is a layout (see assets/site.css) plus a palette. */
final class Presets
{
    public const LAYOUTS = ['rally', 'manuscript', 'night', 'sunday'];

    /** Layout-level defaults for tokens a preset does not set. */
    private const LAYOUT_DEFAULTS = [
        'rally' => ['herobg' => 'transparent', 'altbg' => 'transparent', 'contactbg' => '@fg', 'contactfg' => '@bg'],
        'manuscript' => ['herobg' => 'transparent', 'altbg' => 'transparent', 'contactbg' => 'transparent', 'contactfg' => '@fg'],
        'night' => ['herobg' => 'transparent', 'altbg' => 'transparent', 'contactbg' => '@surface', 'contactfg' => '@fg'],
        'sunday' => ['herobg' => '@hl', 'altbg' => '@surface', 'contactbg' => '@accent', 'contactfg' => '@accent-ink'],
    ];

    public static function all(): array
    {
        return [
            'rally' => [
                'name' => 'Rally', 'note' => 'Bold and sporty', 'layout' => 'rally',
                'bg' => '#E9EEF0', 'surface' => '#F7FAFA', 'fg' => '#0E1F27', 'muted' => '#52646C', 'line' => '#C6D0D4',
                'accent' => '#1F4FE0', 'hl' => '#F4C20D', 'hl-ink' => '#1A1500', 'ph1' => '#B7C9D0', 'ph2' => '#8CA7B2',
            ],
            'gravel' => [
                'name' => 'Gravel', 'note' => 'Earthy olive and orange', 'layout' => 'rally',
                'bg' => '#E8EBE2', 'surface' => '#F5F7F1', 'fg' => '#1E2A1C', 'muted' => '#5B6B57', 'line' => '#C9D1C2',
                'accent' => '#B8500A', 'hl' => '#E8A33D', 'hl-ink' => '#1E1400', 'ph1' => '#BFC9B3', 'ph2' => '#97A68A',
            ],
            'alpine' => [
                'name' => 'Alpine', 'note' => 'Light and minimal', 'layout' => 'rally',
                'bg' => '#F7FAFC', 'surface' => '#FFFFFF', 'fg' => '#12202B', 'muted' => '#5D7080', 'line' => '#D5E0E8',
                'accent' => '#1D74BD', 'hl' => '#BFE3F7', 'hl-ink' => '#12202B', 'ph1' => '#D5E6F0', 'ph2' => '#B3CCDB',
            ],
            'podium' => [
                'name' => 'Podium', 'note' => 'Black and gold', 'layout' => 'night',
                'bg' => '#0B0A08', 'surface' => '#16140F', 'fg' => '#F5F0E1', 'muted' => '#A39B84', 'line' => '#2B2819',
                'accent' => '#D4AF37', 'hl' => '#D4AF37', 'hl-ink' => '#1A1400', 'ph1' => '#2A2517', 'ph2' => '#14120C',
            ],
            'night' => [
                'name' => 'Night Stage', 'note' => 'Dark with a cool accent', 'layout' => 'night',
                'bg' => '#0C0D12', 'surface' => '#15171F', 'fg' => '#F2F3F7', 'muted' => '#9A9FB0', 'line' => '#272A36',
                'accent' => '#5CE1E6', 'hl' => '#5CE1E6', 'hl-ink' => '#04141A', 'ph1' => '#1B2230', 'ph2' => '#0F131B',
            ],
            'neon' => [
                'name' => 'Neon Lap', 'note' => 'Dark with magenta', 'layout' => 'night',
                'bg' => '#0D0A14', 'surface' => '#171122', 'fg' => '#F4EEFF', 'muted' => '#A398BB', 'line' => '#2A2040',
                'accent' => '#FF3DAE', 'hl' => '#FF3DAE', 'hl-ink' => '#1A0010', 'ph1' => '#241838', 'ph2' => '#120C1D',
            ],
            'manuscript' => [
                'name' => 'Manuscript', 'note' => 'Quiet and text first', 'layout' => 'manuscript',
                'bg' => '#FFFFFF', 'surface' => '#F4F5F6', 'fg' => '#16181D', 'muted' => '#5E6470', 'line' => '#DADCE0',
                'accent' => '#0F6B4F', 'hl' => '#0F6B4F', 'hl-ink' => '#FFFFFF', 'ph1' => '#D9DEE3', 'ph2' => '#B9C2CB',
            ],
            'inkwell' => [
                'name' => 'Inkwell', 'note' => 'Deep navy for writers', 'layout' => 'manuscript',
                'bg' => '#0F1B2D', 'surface' => '#16263D', 'fg' => '#E8EEF7', 'muted' => '#9FB0C8', 'line' => '#25395A',
                'accent' => '#E9A23B', 'hl' => '#E9A23B', 'hl-ink' => '#1A1100', 'ph1' => '#1F3555', 'ph2' => '#142842',
            ],
            'sunday' => [
                'name' => 'Sunday', 'note' => 'Loud and friendly', 'layout' => 'sunday',
                'bg' => '#FFFFFF', 'surface' => '#F1EFFF', 'fg' => '#151515', 'muted' => '#4B4B55', 'line' => '#151515',
                'accent' => '#2B3CFF', 'hl' => '#FFD23F', 'hl-ink' => '#151515', 'ph1' => '#FFD23F', 'ph2' => '#FF8A5C',
            ],
            'sorbet' => [
                'name' => 'Sorbet', 'note' => 'Soft pink and teal', 'layout' => 'sunday',
                'bg' => '#FFFFFF', 'surface' => '#E6F7F5', 'fg' => '#1B1B3A', 'muted' => '#4F4F70', 'line' => '#1B1B3A',
                'accent' => '#0B7F85', 'hl' => '#FFB3D1', 'hl-ink' => '#1B1B3A', 'ph1' => '#FFB3D1', 'ph2' => '#8ADAD3',
            ],
        ];
    }

    public static function get(string $id): array
    {
        $all = self::all();
        return $all[$id] ?? $all['rally'];
    }

    public static function id(): string
    {
        $id = (string)Settings::get('preset', 'rally');
        return isset(self::all()[$id]) ? $id : 'rally';
    }

    public static function layout(): string
    {
        return self::get(self::id())['layout'];
    }

    /** Palette tokens after the owner's overrides, ready to print as CSS. */
    public static function tokens(): array
    {
        $p = self::get(self::id());
        $t = [
            'bg' => $p['bg'], 'surface' => $p['surface'], 'fg' => $p['fg'], 'muted' => $p['muted'], 'line' => $p['line'],
            'accent' => $p['accent'], 'hl' => $p['hl'], 'hl-ink' => $p['hl-ink'], 'ph1' => $p['ph1'], 'ph2' => $p['ph2'],
        ];
        $derived = [];
        foreach (['bg', 'fg'] as $k) {
            $v = Settings::get('color_' . $k, '');
            if (hex_valid($v)) {
                $t[$k] = $v;
                $derived['colors'] = true;
            }
        }
        if (!empty($derived['colors'])) {
            $t['surface'] = 'color-mix(in srgb, ' . $t['bg'] . ' 92%, ' . $t['fg'] . ')';
            $t['muted'] = 'color-mix(in srgb, ' . $t['fg'] . ' 62%, ' . $t['bg'] . ')';
            $t['line'] = 'color-mix(in srgb, ' . $t['fg'] . ' 18%, ' . $t['bg'] . ')';
        }
        $accent = Settings::get('color_accent', '');
        if (hex_valid($accent)) {
            $t['accent'] = $accent;
        }
        $t['accent-ink'] = self::inkFor($t['accent']);
        $t['outline'] = $t['fg'];
        foreach (self::LAYOUT_DEFAULTS[$p['layout']] as $k => $v) {
            $t[$k] = str_starts_with($v, '@') ? ($t[substr($v, 1)] ?? $v) : $v;
        }
        $t['contour'] = 'color-mix(in srgb, ' . $t['fg'] . ' 14%, transparent)';
        return $t;
    }

    public static function css(): string
    {
        $out = '';
        foreach (self::tokens() as $k => $v) {
            $out .= '--s-' . $k . ':' . $v . ';';
        }
        return '.site{' . $out . '}';
    }

    /** Readable text colour (white or near-black) for a background colour. */
    public static function inkFor(string $hex): string
    {
        if (!hex_valid($hex)) {
            return '#FFFFFF';
        }
        return self::contrast($hex, '#FFFFFF') >= self::contrast($hex, '#111111') ? '#FFFFFF' : '#111111';
    }

    public static function luminance(string $hex): float
    {
        $c = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $c = array_map(static function ($v) {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $c);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** Human-readable warnings for the Appearance page. */
    public static function warnings(): array
    {
        $p = self::get(self::id());
        $bg = hex_valid(Settings::get('color_bg', '')) ? (string)Settings::get('color_bg') : $p['bg'];
        $fg = hex_valid(Settings::get('color_fg', '')) ? (string)Settings::get('color_fg') : $p['fg'];
        $accent = hex_valid(Settings::get('color_accent', '')) ? (string)Settings::get('color_accent') : $p['accent'];
        $w = [];
        if (self::contrast($fg, $bg) < 4.5) {
            $w[] = 'Text and background are too similar. Body text will be hard to read (contrast ' . round(self::contrast($fg, $bg), 1) . ':1, aim for 4.5:1 or more).';
        }
        if (self::contrast($accent, $bg) < 3) {
            $w[] = 'The accent colour is hard to see on the background (contrast ' . round(self::contrast($accent, $bg), 1) . ':1, aim for 3:1 or more). Links and active menu items may disappear.';
        }
        return $w;
    }
}
