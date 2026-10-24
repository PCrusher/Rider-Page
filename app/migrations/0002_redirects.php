<?php
declare(strict_types=1);

return static function (): void {
    // Old address -> new address, set up by the owner.
    Db::createTable('redirects', [
        Db::pk(),
        'from_path VARCHAR(190) NOT NULL UNIQUE',
        'to_url VARCHAR(500) NOT NULL',
        'code INT NOT NULL DEFAULT 301',
        'hits INT NOT NULL DEFAULT 0',
        'created_at VARCHAR(25) NOT NULL',
    ]);

    // Addresses that visitors asked for but that do not exist. No IP addresses or visitor data are stored.
    Db::createTable('not_found', [
        Db::pk(),
        'path VARCHAR(190) NOT NULL UNIQUE',
        'hits INT NOT NULL DEFAULT 1',
        'last_seen VARCHAR(25) NOT NULL',
    ]);
};
