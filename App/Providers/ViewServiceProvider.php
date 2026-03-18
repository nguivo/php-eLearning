<?php

namespace App\Providers;

use App\Core\Container;
use App\Core\ServiceProvider;
use App\Core\View;

class ViewServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->container->singleton(View::class, function (Container $c): View {
            return new View(
                viewsPath:     BASE_PATH . '/App/Views',
                defaultLayout: 'layouts/main'
            );
        });
    }
}