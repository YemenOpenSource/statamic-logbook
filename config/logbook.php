<?php

return [
    'db' => [
        // null => use default DB connection
        'connection' => env('LOGBOOK_DB_CONNECTION', null),
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
