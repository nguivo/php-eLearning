<?php

declare(strict_types=1);

/**
 * bootstrap/app.php — Application Bootstrap
 *
 * Builds the Application instance, registers all Service Providers,
 * and returns the ready-to-run Application to the caller.
 *
 * constants.php and the autoloader must already be loaded before this file
 * is required. public/index.php handles that ordering.
 *
 * Returns the Application instance so public/index.php can call $app->run().
 *
 * To add a new Service Provider:
 *   1. Create App/Providers/YourServiceProvider.php extending ServiceProvider
 *   2. Add it to the array below in the correct order
 *      (providers are booted in the order they are listed)
 */

use App\Core\Application;

$app = new Application();

$app->registerProviders([
    // Infrastructure first — other providers depend on these
    App\Providers\DatabaseServiceProvider::class,
    App\Providers\ViewServiceProvider::class,
    App\Providers\RouteServiceProvider::class,

    // Application-level bindings last
    App\Providers\AppServiceProvider::class,
]);