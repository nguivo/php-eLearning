<?php

declare(strict_types=1);

namespace App\Core;

abstract class ServiceProvider
{
    /*
     * Base class for all service providers
     * */

    public function __construct(protected Container $container) {}

    abstract public function register(): void;

    public function boot(): void {}

}