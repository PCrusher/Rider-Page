<?php
declare(strict_types=1);

return static function (): void {
    // Visit statistics without cookies. No IP address is stored: "vh" is a hash that changes every day,
    // so a visitor can be counted once per day but never followed from one day to the next.
    Db::createTable('hits', [
        Db::pk(),
        'day VARCHAR(10) NOT NULL',
        'ts INT NOT NULL',
        "kind VARCHAR(6) NOT NULL DEFAULT 'page'",
        'vh VARCHAR(16) NOT NULL',
        "path VARCHAR(190) NOT NULL DEFAULT ''",
        "ref VARCHAR(120) NOT NULL DEFAULT ''",
        "dev VARCHAR(10) NOT NULL DEFAULT ''",
        "br VARCHAR(16) NOT NULL DEFAULT ''",
        "target VARCHAR(100) NOT NULL DEFAULT ''",
    ]);
    Db::pdo()->exec('CREATE INDEX hits_day ON hits (day)');

    // Daily totals of days whose detailed rows were already deleted.
    Db::createTable('hit_days', [
        'day VARCHAR(10) NOT NULL PRIMARY KEY',
        'views INT NOT NULL DEFAULT 0',
        'visitors INT NOT NULL DEFAULT 0',
    ]);
};
