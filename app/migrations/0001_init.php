<?php
declare(strict_types=1);

return static function (): void {
    Db::createTable('settings', ['k VARCHAR(100) NOT NULL PRIMARY KEY', 'v ' . Db::longText()]);

    Db::createTable('users', [
        Db::pk(),
        'email VARCHAR(190) NOT NULL UNIQUE',
        'password_hash VARCHAR(255) NOT NULL',
        'created_at VARCHAR(25) NOT NULL',
    ]);

    Db::createTable('login_attempts', [Db::pk(), 'ip VARCHAR(64) NOT NULL', 'ts INT NOT NULL']);

    Db::createTable('socials', [
        Db::pk(),
        "type VARCHAR(40) NOT NULL DEFAULT 'website'",
        "handle VARCHAR(190) NOT NULL DEFAULT ''",
        'url VARCHAR(500) NOT NULL',
        'pos INT NOT NULL DEFAULT 0',
    ]);

    Db::createTable('sponsors', [
        Db::pk(),
        'name VARCHAR(190) NOT NULL',
        "tier VARCHAR(20) NOT NULL DEFAULT 'partner'",
        "url VARCHAR(500) NOT NULL DEFAULT ''",
        "logo VARCHAR(255) NOT NULL DEFAULT ''",
        'pos INT NOT NULL DEFAULT 0',
    ]);

    Db::createTable('stats', [
        Db::pk(),
        'figure VARCHAR(60) NOT NULL',
        'label VARCHAR(190) NOT NULL',
        'pos INT NOT NULL DEFAULT 0',
    ]);

    Db::createTable('media', [
        Db::pk(),
        'image VARCHAR(255) NOT NULL',
        "caption VARCHAR(255) NOT NULL DEFAULT ''",
        'pos INT NOT NULL DEFAULT 0',
    ]);

    Db::createTable('faqs', [
        Db::pk(),
        'question VARCHAR(255) NOT NULL',
        'answer ' . Db::longText() . ' NOT NULL',
        'pos INT NOT NULL DEFAULT 0',
    ]);

    Db::createTable('posts', [
        Db::pk(),
        'slug VARCHAR(190) NOT NULL UNIQUE',
        'title VARCHAR(255) NOT NULL',
        "summary VARCHAR(500) NOT NULL DEFAULT ''",
        "cover VARCHAR(255) NOT NULL DEFAULT ''",
        'body ' . Db::longText() . ' NOT NULL',
        "status VARCHAR(12) NOT NULL DEFAULT 'draft'",
        'published_at VARCHAR(25) NOT NULL',
        'created_at VARCHAR(25) NOT NULL',
        'updated_at VARCHAR(25) NOT NULL',
    ]);
};
