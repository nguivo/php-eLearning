<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use InvalidArgumentException;


/**
 * Router
 *
 * Handles URL routing for the entire platform
 *
 * The Router's job is purely orchestration:
 * - Accept route registration and build Route objects
 * - Manage the group stack (prefix + middleware inheritance)
 * - Maintain the named-route index for URL generation
 * - On dispatch: find the matching Route, run its middleware, call its
 * - handler
 * */
class Router
{
    // -----------------------------------------------------
    // Properties
    // -----------------------------------------------------
    private array $routes = [];
    private array $groupStack = [];
    private array $namedRoutes = [];
    private string $controllerNamespace = 'App\\Controllers\\';


    // ---------------------------------------------------
    // Route Registration - Public API
    // ---------------------------------------------------

    /* Example: $router->get('/courses', 'CourseController@index') */
    public function get(string $path, Closure|string $handler): Route
    {
        return
            $this->addRoute('GET', $path, $handler);
    }


    public function post(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }


    /**
     * Used for full resource replacement (all fields sent)
     * Example: $router->put('/courses/{id:\d+}', 'CourseController@update';
     * */
    public function put(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }


    /** Used for partial resource updates (only changed fields sent) */
    public function patch(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }


    public function delete(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }


    /** Routes that respond to any HTTP method. Useful for API endpoints that handle their own method-switching internally */
    public function any(string $path, Closure|string $handler): self
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }


    // ---------------------------------------------------------
    // Fluent Chaining - middleware() and name()
    // ---------------------------------------------------------

    /* Add middleware to the most recently registered route */
    public function middleware(array $middlewareList): self
    {
        $this->lastRoute()?->addMiddleware($middlewareList);
        return $this;
    }

    /* Name the most recently registered route */
    public function name(string $name): self
    {
        $route = $this->lastRoute();
        if ($route !== null) {
            $route->setName($name);
            $this->namedRoutes[$name] = $route;
        }
        return $this;
    }


    // -------------------------------------------------------
    // Route Groups
    // -------------------------------------------------------

    /**
     *  Group routes under a shared URL prefix and/or middleware
     * Groups nest correctly - each inner group inherits and extends
     * the outer group's cumulative prefix and middleware list.
     **/
    public function group(array $options, Closure $callback): void
    {
        $parentGroup = end($this->groupStack) ?: ['prefix' => '', 'middleware' => []];

        $newGroup = [
            'prefix' => ($parentGroup['prefix'] ?? '').($options['prefix'] ?? ''),
            'middleware' => array_merge($parentGroup['middleware'] ?? [], $options['middleware'] ?? [])
        ];

        $this->groupStack[] = $newGroup;
        $callback($this);
        array_pop($this->groupStack);
    }


    // -------------------------------------------------------
    // URL Generation
    // -------------------------------------------------------

    /**
     * Generate a URL for a named route, substituting in parameters
     *
     * $router->get('/courses/{id:\d+}/lessons/{lessonId:\d+}', '...')
     *          ->name('lesson.show');
     *
     * $url = $router->route('lesson.show', ['id'=>5,'lessonId'=>12]);
     * // -> '/courses/5/lessons/12'
     *
     * @throws InvalidArgumentException if the name has not
     * been registered
     * */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException(
                "No route registered with name: '{$name}'".
                "Availabel names: ". implode(', ', array_keys($this->namedRoutes))
            );
        }

        // Retrieve the Route object
        $route = $this->namedRoutes[$name];
        $path = $route->getPath();

        // Substitute each {param} or {param:regex} with its supplied value
        foreach ($params as $key => $value) {
            $path = preg_replace("#\{{$key}(?::[^}]+)?\}#", (string) $value, $path);
        }

        // Remove any optional placeholders that were not supplied
        $path = preg_replace('#\{[^}]+\?\}#', '', $path);

        // Collapse any double-slashes left behind by removed optional segments
        $path = preg_replace('#/{2,}#', '/', $path);

        return rtrim($path, '/') ?: '/';
    }


    // ----------------------------------------------------------
    // Dispatching
    // ----------------------------------------------------------

    /**
     * match the incoming request to a Route and run it.
     *
     * This method:
     * 1. iterates over Route objects
     * 2. Calls $route->matchesPath() - to distinguish 404 from 405
     * 3. Calls $route->matches() - done by Route
     * 4. Calls $route->extractParams()
     * 5. Passes control to runMiddleware() -> callHandler()
     * */
    public function dispatch(Request $request, Response $response): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        $matches = [];

        // collect HTTP methods that have a matching path (for 405 allow header
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            // Does this route's path pattern match? (ignores HTTP method)
            if (!$route->matchesPath($path)) {
                continue;
            }

            // if path matches - record its method for a potential 405 response
            $allowedMethods[] = $route->getMethod();

            // Now check the full match (path + method)
            if (!$route->matches($method, $path, $matches)) {
                continue;
            }

            // Full Match - extract named URL params and inject into Request
            $params = $route->extractParams($matches);
            $request->setRouteParams($params);

            // Run middleware pipeline -> handler at the centre
            $this->runMiddleware(
                $route->getMiddleware(),
                $request,
                $response,
                fn() => $this->callHandler($route->getHandler(), $request, $response)
            );

            return;
        }

        // No match found
        if (!empty($allowedMethods)) {
            $this->handleMethodNotAllowed($response, $allowedMethods);
        } else {
            $this->handleNotFound($response);
        }
    }


    // ------------------------------------------------------
    // Private Helpers
    // ------------------------------------------------------

    /**
     * Instantiate a Route object and register it
     * */
    private function addRoute(string $method, string $path, Closure|string $handler): Route
    {
        $group = end($this->groupStack) ?: ['prefix' => '', 'middleware' => []];

        $fullPath = $this->normalisePath($group['prefix'].$path);

        $route = new Route(
            method: $method,
            path: $fullPath,
            handler: $handler,
            middleware: $group['middleware']
        );

        $this->routes[] = $route;
        return $route; // returned so callers can chain ->middleware() and name()
    }


    /*
     * return the most recently registered object or null if none exist.
     * */
    private function lastRoute(): ?Route
    {
        $last = end($this->routes);
        return $last !== false ? $last : null;
    }


    /*
     * Execute the middleware pipeline using the "onion" pattern.
     * */
    private function runMiddleware(array $middlewareList, Request $request, Response $response, Closure $core): void
    {
        $pipeline = array_reduce(
            array_reverse($middlewareList),
            function (Closure $carry, string $middlewareClass) use ($request, $response): Closure {
                return function () use ($carry, $middlewareClass, $request, $response): void {
                    $middleware = new $middlewareClass();
                    $middleware->handle($request, $response, $carry);
                };
            },
            $core
        );

        $pipeline();
    }


    /**
     * Resolve and invoke the route handler
     * */
    private function callHandler(Closure|string $handler, Request $request, Response $response): void
    {
        if ($handler instanceof Closure) {
            $handler($request, $response);
            return;
        }

        if (!str_contains($handler, '@')) {
            throw new \InvalidArgumentException(
                "Handler '{$handler}' is invalid. Expected 'ControllerClass@method' format."
            );
        }

        [$controllerName, $method] = explode('@', $handler, 2);
        $controllerClass = $this->controllerNamespace.$controllerName;

        if (!class_exists($controllerClass)) {
            throw new \InvalidArgumentException(
                "Controller class '{$controllerClass}' not found. " . "Check the class name and namespace in your route definition."
            );
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException(
                "Method '{$method}' does not exist on '{$controllerClass}'."
            );
        }

        $controller->$method($request, $response);
    }


    /*
     * Normalise a URL path:
     * */
    private function normalisePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#/{2,}#', '/', $path);
        return $path === '/' ? $path : rtrim($path, '/');
    }


    /** Send a 404 Not Found response */
    private function handleNotFound(Response $response): void
    {
        $response->setStatusCode(404);
        $response->setBody('<h1>404 — Page Not Found</h1>');
        $response->send();
    }

    /**
     * Send a 405 Method Not Allowed response.
     * The HTTP spec requires an Allow header listing the valid methods for the path.
     */
    private function handleMethodNotAllowed(Response $response, array $allowedMethods): void
    {
        $response->setStatusCode(405);
        $response->setHeader('Allow', implode(', ', array_unique($allowedMethods)));
        $response->setBody('<h1>405 — Method Not Allowed</h1>');
        $response->send();
    }


}




















