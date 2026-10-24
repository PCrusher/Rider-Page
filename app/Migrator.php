<?php
declare(strict_types=1);

/**
 * Runs the numbered files in app/migrations in order.
 *
 * Rules for migration files (keep updates safe for existing sites):
 *  - only add tables and columns, give every new column a default;
 *  - never rename or drop something an older version still reads;
 *  - new features must work with their defaults and stay hidden until set up.
 */
final class Migrator
{
    public static function files(): array
    {
        $files = glob(RP_APP . '/migrations/*.php') ?: [];
        sort($files);
        return $files;
    }

    public static function latest(): string
    {
        $files = self::files();
        return $files ? basename((string)end($files), '.php') : '';
    }

    public static function current(): string
    {
        $marker = RP_STORAGE . '/schema.txt';
        return is_file($marker) ? trim((string)file_get_contents($marker)) : '';
    }

    public static function runIfNeeded(): void
    {
        if (self::current() !== self::latest()) {
            self::run();
        }
    }

    /** @return string[] ids of the migrations that were applied now */
    public static function run(): array
    {
        $lockFile = RP_STORAGE . '/migrate.lock';
        $lock = @fopen($lockFile, 'c');
        if ($lock) {
            flock($lock, LOCK_EX);
        }
        $done = [];
        try {
            Db::createTable('migrations', ['id VARCHAR(100) NOT NULL PRIMARY KEY', 'applied_at VARCHAR(25) NOT NULL']);
            $applied = array_column(Db::rows('SELECT id FROM migrations'), 'id');
            foreach (self::files() as $file) {
                $id = basename($file, '.php');
                if (in_array($id, $applied, true)) {
                    continue;
                }
                $fn = require $file;
                $fn();
                Db::insert('migrations', ['id' => $id, 'applied_at' => now()]);
                $done[] = $id;
            }
            file_put_contents(RP_STORAGE . '/schema.txt', self::latest());
            Settings::reset();
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        return $done;
    }
}
