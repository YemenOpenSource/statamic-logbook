<?php

namespace EmranAlhaddad\StatamicLogbook\Support;

class DbConnectionResolver
{
    public static function resolve(): string
    {
        $configured = config('logbook.db.connection');

        return $configured ?: config('database.default');
    }
}
