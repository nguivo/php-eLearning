<?php

declare(strict_types=1);

/**
 * bootstrap/constants.php — Application Path Constants
 *
 * This is the single source of truth for every filesystem path constant
 * used throughout the application. It is loaded before the container, before any class is built.
 *
 * Rules:
 *   - Define constants here and ONLY here. Never call define() anywhere else.
 *   - All paths are absolute. Never build paths with relative strings elsewhere.
 *   - Add a new constant here when a new top-level directory is introduced.
 *
 */

// Project root — one level above /public
define('BASE_PATH', dirname(__DIR__));

// Application source
define('APP_PATH',     BASE_PATH . '/App');
define('CONFIG_PATH',  BASE_PATH . '/App/Config');
define('VIEWS_PATH',   BASE_PATH . '/App/Views');
define('ROUTES_PATH',  BASE_PATH . '/App/Routes');

// Runtime directories
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH',  BASE_PATH . '/public');
define('LOGS_PATH',    BASE_PATH . '/logs');

// Directory Separator
define('DS', DIRECTORY_SEPARATOR);


// Enables the rest of the application to access the values inside
// .env file without ever touching $_ENV
// $_ENV is only called within config files and nowhere else in the app
function config(string $key, mixed $default = null): mixed
{
    static $config = [];

    // key format: 'database.host', 'mail.port', 'app.debug'
    [$file, $setting] = explode('.', $key, 2);

    if (!isset($config[$file])) {
        $path = CONFIG_PATH . DS . $file . '.php';
        $config[$file] = file_exists($path) ? require $path : [];
    }

    return $config[$file][$setting] ?? $default;
}
