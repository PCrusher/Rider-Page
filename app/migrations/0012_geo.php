<?php
declare(strict_types=1);

return static function (): void {
    // Country and city of a visit, taken from an offline database on this server. The IP address itself is not stored.
    Db::addColumn('hits', 'cc', "VARCHAR(2) NOT NULL DEFAULT ''");
    Db::addColumn('hits', 'country', "VARCHAR(60) NOT NULL DEFAULT ''");
    Db::addColumn('hits', 'city', "VARCHAR(80) NOT NULL DEFAULT ''");
};
