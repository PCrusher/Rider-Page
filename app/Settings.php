<?php
declare(strict_types=1);

/** Key/value site settings stored in the database. */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Db::rows('SELECT k, v FROM settings') as $r) {
                    self::$cache[(string)$r['k']] = (string)$r['v'];
                }
            } catch (Throwable) {
                // Table does not exist yet (before the first migration).
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Db::delete('settings', 'k = ?', [$key]);
        Db::insert('settings', ['k' => $key, 'v' => $value]);
        self::$cache = null;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            self::set((string)$k, (string)$v);
        }
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
