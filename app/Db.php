<?php
declare(strict_types=1);

/** Thin PDO wrapper that works the same on SQLite and MySQL/MariaDB. */
final class Db
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';

    public static function connect(array $cfg): void
    {
        self::$driver = ($cfg['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (self::$driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . $cfg['path'], null, null, $opts);
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . (int)($cfg['port'] ?? 3306)
                . ';dbname=' . $cfg['name'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, (string)$cfg['user'], (string)$cfg['pass'], $opts);
        }
        self::$pdo = $pdo;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Database is not connected.');
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }

    public static function rows(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::run($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): void
    {
        $set = implode(', ', array_map(static fn($c) => $c . ' = ?', array_keys($data)));
        self::run('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where, [...array_values($data), ...$params]);
    }

    public static function delete(string $table, string $where, array $params = []): void
    {
        self::run('DELETE FROM ' . $table . ' WHERE ' . $where, $params);
    }

    /* Helpers for migrations */

    public static function pk(): string
    {
        return self::$driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    }

    public static function longText(): string
    {
        return self::$driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT';
    }

    public static function createTable(string $name, array $columns): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $name . ' (' . implode(', ', $columns) . ')';
        if (self::$driver === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        self::pdo()->exec($sql);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (self::$driver === 'sqlite') {
            foreach (self::rows('PRAGMA table_info(' . $table . ')') as $r) {
                if (strcasecmp((string)$r['name'], $column) === 0) {
                    return true;
                }
            }
            return false;
        }
        return (int)self::val(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        ) > 0;
    }

    public static function addColumn(string $table, string $column, string $definition): void
    {
        if (!self::hasColumn($table, $column)) {
            self::pdo()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    public static function tables(): array
    {
        if (self::$driver === 'sqlite') {
            return array_column(self::rows("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"), 'name');
        }
        return array_map('current', self::rows('SHOW TABLES'));
    }
}
