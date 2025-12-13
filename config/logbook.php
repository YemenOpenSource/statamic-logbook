<?php

return [
    'db' => [
        'connection' => [
            'driver' => env('LOGBOOK_DB_CONNECTION'),
            'host' => env('LOGBOOK_DB_HOST'),
            'port' => env('LOGBOOK_DB_PORT', '3306'),
            'database' => env('LOGBOOK_DB_DATABASE'),
            'username' => env('LOGBOOK_DB_USERNAME'),
            'password' => env('LOGBOOK_DB_PASSWORD'),
            'unix_socket' => env('LOGBOOK_DB_SOCKET', ''),
            'charset' => env('LOGBOOK_DB_CHARSET', 'utf8mb4'),
            'collation' => env('LOGBOOK_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],

    'system_logs' => [
        'enabled' => env('LOGBOOK_SYSTEM_ENABLED', true),
    ],

    'audit_logs' => [
        'enabled' => env('LOGBOOK_AUDIT_ENABLED', true),

        // fields to ignore when computing diffs (noise)
        'ignore_fields' => array_filter(array_map('trim', explode(',', env(
            'LOGBOOK_AUDIT_IGNORE_FIELDS',
            'updated_at,created_at,date,uri,slug'
        )))),

        // avoid huge payloads in DB
        'max_value_length' => (int) env('LOGBOOK_AUDIT_MAX_VALUE_LENGTH', 2000),
    ],

    'retention_days' => (int) env('LOGBOOK_RETENTION_DAYS', 365),
];
