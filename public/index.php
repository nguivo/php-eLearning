<?php
    declare(strict_types=1);

    /**
     * Public/index.php - Application Entry Point
     *
     * .htaccess routes every request here
     * this file boots the app, loads routes, and dispatches.
     * */

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

    require_once dirname(__DIR__)."/vendor/autoload.php";

    // Environment Variables (.env file)
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();

    // Core objects
    $request = new Request();
    $response = new Response();
    $router = new Router();

    // Load route definitions
    require_once dirname(__DIR__).'/App/Routes/web.php';

    // Dispatch = match the request to a route and run it
    $router->dispatch($request, $response);

