<?php

return [
    'backups' => [
        'enabled' => (bool) env('BACKUP_ENABLED', false),
        'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
        'mysql_dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
        'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    ],
    'media' => [
        'orphan_grace_hours' => (int) env('MEDIA_ORPHAN_GRACE_HOURS', 24),
    ],
];
