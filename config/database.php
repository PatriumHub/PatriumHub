<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'host' => patrium_env('DB_HOST', '127.0.0.1'),
    'port' => (int) patrium_env('DB_PORT', '3306'),
    'name' => patrium_env('DB_NAME', 'patriumhub'),
    'user' => patrium_env('DB_USER', 'root'),
    'pass' => patrium_env('DB_PASS', '') ?? '',
    'charset' => 'utf8mb4',
];
