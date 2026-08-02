<?php

return [
    'backups' => [
        'enabled' => (bool) env('BACKUP_ENABLED', false),
        'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
        'mysql_dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
        'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
        // Сколько последних копий хранить. Копия включает оригиналы медиа, то
        // есть растёт вместе с библиотекой: без обрезки диск заполняется, и
        // первым падает как раз следующий бэкап.
        'keep' => max(1, (int) env('BACKUP_KEEP', 7)),
    ],
    'media' => [
        'orphan_grace_hours' => (int) env('MEDIA_ORPHAN_GRACE_HOURS', 24),
    ],
];
