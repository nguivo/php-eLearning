<?php

declare(strict_types=1);

/**
 * bootstrap/constants.php — Application Path Constants
 *
 * This is the single source of truth for every filesystem path constant
 * used throughout the application. It is loaded before anything else —
 * before the autoloader, before the container, before any class is built.
 *
 * Rules:
 *   - Define constants here and ONLY here. Never call define() anywhere else.
 *   - All paths are absolute. Never build paths with relative strings elsewhere.
 *   - Add a new constant here when a new top-level directory is introduced.
 *
 * Usage anywhere in the application:
 *   BASE_PATH    — project root:          /var/www/eLearning
 *   APP_PATH     — App/ directory:        /var/www/eLearning/App
 *   CONFIG_PATH  — App/Config/:           /var/www/eLearning/App/Config
 *   VIEWS_PATH   — App/Views/:            /var/www/eLearning/App/Views
 *   ROUTES_PATH  — App/Routes/:           /var/www/eLearning/App/Routes
 *   STORAGE_PATH — storage/:              /var/www/eLearning/storage
 *   PUBLIC_PATH  — public/:               /var/www/eLearning/public
 *   LOGS_PATH    — logs/:                 /var/www/eLearning/logs
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
