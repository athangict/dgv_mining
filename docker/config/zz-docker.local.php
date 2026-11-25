<?php
/**
 * Docker / Dokploy deployment overrides. NOT part of the application code.
 *
 * The Dockerfile copies this file into config/autoload/ inside the image.
 * Laminas loads config/autoload/*.local.php alphabetically after everything
 * else, so "zz-" makes these values win without editing any app file.
 */

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

$isProduction = strtolower(trim((string) $env('APP_ENV', 'local'))) === 'production';

return [
    // config/autoload/local.php builds the DSN with "hostname=", which PDO
    // MySQL ignores (it expects "host="), so it silently falls back to a local
    // socket and cannot reach a database in another container. Same DSN,
    // same env vars, correct key.
    'db' => [
        'dsn' => sprintf(
            'mysql:dbname=%s;host=%s;port=%s',
            $env('DB_NAME', 'dheyma_mining_24'),
            $env('DB_HOST', '127.0.0.1'),
            $env('DB_PORT', '3306')
        ),
    ],

    // Module configs and development.local.php set display_exceptions=true,
    // which prints stack traces (file paths, SQL) to visitors. Hide in production.
    'view_manager' => [
        'display_exceptions'       => ! $isProduction,
        'display_not_found_reason' => ! $isProduction,
    ],
];
