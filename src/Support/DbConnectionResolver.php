<?php

namespace EmranAlhaddad\StatamicLogbook\Support;

class DbConnectionResolver
{
    public static function resolve(): string
    {
        $name = 'logbook';
        $cfg  = config('logbook.db.connection');

        // Register runtime connection (no database.php edits required)
        config(["database.connections.$name" => $cfg]);

        return $name;
    }
}
