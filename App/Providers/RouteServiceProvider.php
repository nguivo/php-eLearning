<?php

namespace App\Providers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ServiceProvider;
use App\Core\View;

class RouteServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->container->instance(Request::class, new Request());
        $this->container->instance(Response::class, new Response());

        $this->container->singleton(Router::class, function ($c): Router {
            return new Router($c->make(View::class));
        });
    }


    public function boot(): void
    {
        $router = $this->container->make(Router::class);
        require_once ROUTES_PATH . '/web.php';
    }
}