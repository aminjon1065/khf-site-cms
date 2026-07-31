<?php

return [
    'php_fpm_max_children' => (int) env('PHP_FPM_MAX_CHILDREN', 0),
    'opcache' => [
        'memory_consumption' => (int) env('OPCACHE_MEMORY_CONSUMPTION', 192),
        'max_accelerated_files' => (int) env('OPCACHE_MAX_ACCELERATED_FILES', 20_000),
    ],
];
