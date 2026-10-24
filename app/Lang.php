<?php
declare(strict_types=1);

/** Texts that the template itself prints (menu, headings, buttons). The owner's own content is never translated. */
final class Lang
{
    private static ?array $strings = null;
    private static ?array $fallback = null;

    public const AVAILABLE = ['en' => 'English', 'de' => 'Deutsch'];

    public static function code(): string
    {
        $c = (string)Settings::get('lang', 'en');
        return isset(self::AVAILABLE[$c]) ? $c : 'en';
    }

    public static function t(string $key, array $vars = []): string
    {
        self::$strings ??= self::load(self::code());
        self::$fallback ??= self::load('en');
        $s = self::$strings[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($vars as $k => $v) {
            $s = str_replace('{' . $k . '}', (string)$v, $s);
        }
        return $s;
    }

    public static function reset(): void
    {
        self::$strings = null;
    }

    private static function load(string $code): array
    {
        $file = RP_APP . '/lang/' . $code . '.php';
        return is_file($file) ? (array)require $file : [];
    }
}
