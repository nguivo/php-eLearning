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
                viewsPath:     VIEWS_PATH,
                defaultLayout: 'layouts/main'
            );
        });
    }


    public function boot(): void
    {
        $view = $this->container->make(View::class);
        $view->share('appName', config('app.name'));
        $view->share('appEnv', config('app.env'));
    }

}