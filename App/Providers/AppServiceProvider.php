<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\ServiceProvider;

/**
 * AppServiceProvider
 *
 * Home for application-level bindings that don't belong in a more specific provider.
 * Register interface → concrete bindings here, or split into dedicated providers
 * as the app grows (RepositoryServiceProvider, AuthServiceProvider, etc.)
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Example:
        // $this->container->singleton(
        //     \App\Contracts\MailerInterface::class,
        //     \App\Services\EmailService::class
        // );
    }

    public function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
