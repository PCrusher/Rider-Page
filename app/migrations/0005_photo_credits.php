<?php
declare(strict_types=1);

return static function (): void {
    // Optional photographer name and link for gallery photos and blog covers.
    // (The main photo's credit lives in the settings table.)
    Db::addColumn('media', 'credit', "VARCHAR(190) NOT NULL DEFAULT ''");
    Db::addColumn('media', 'credit_url', "VARCHAR(500) NOT NULL DEFAULT ''");
    Db::addColumn('posts', 'cover_credit', "VARCHAR(190) NOT NULL DEFAULT ''");
    Db::addColumn('posts', 'cover_credit_url', "VARCHAR(500) NOT NULL DEFAULT ''");
};
