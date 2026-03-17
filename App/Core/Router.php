<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Router
 *
 * Registers routes and dispatches incoming requests to the correct handler.
 *
 * Each route is a Route object and not an associative array.
 * The Router's job is purely orchestration:
 *   - Accept route registrations and build Route objects
 *   - Manage the group stack (prefix + middleware inheritance)
 *   - Maintain the named-route index for URL generation
 *   - On dispatch: find the matching Route, run its middleware, call its handler
 *
 * The Route class owns all route-specific logic:
 *   matching, pattern compilation, and parameter extraction.
 */
class Router
{
    private array $routes = [];
    private View $view;
    private array $groupStack = [];
    private array $namedRoutes = [];
    private string $controllerNamespace = 'App\\Controllers\\';


    public function __construct(View $view)
    {
        $this->view = $view;
    }


    // -------------------------------------------------------------------------
    // Route Registration — Public API
    // -------------------------------------------------------------------------

    public function get(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }


    public function post(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }


    public function put(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }


    public function patch(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }


    public function delete(string $path, Closure|string $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }


    public function any(string $path, Closure|string $handler): self
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }


    /**
     * Add middleware to the most recently registered route.
     * Delegates to Route::addMiddleware() to merge the new middleware with any existing middleware on the Route.
     */
    public function middleware(array $middlewareList): self
    {
        $this->lastRoute()?->addMiddleware($middlewareList);
        return $this;
    }


    // -------------------------------------------------------------------------
    // Route Groups
    // -------------------------------------------------------------------------

    /**
     * Group routes under a shared URL prefix and/or middleware.
     */
    public function group(array $options, Closure $callback): void
    {
        $parentGroup = end($this->groupStack) ?: ['prefix' => '', 'middleware' => []];

        $newGroup = [
            'prefix'     => ($parentGroup['prefix'] ?? '') . ($options['prefix'] ?? ''),
            'middleware' => array_merge($parentGroup['middleware'] ?? [], $options['middleware'] ?? []),
        ];

        $this->groupStack[] = $newGroup;
        $callback($this);
        array_pop($this->groupStack);
    }


    // -------------------------------------------------------------------------
    // URL Generation
    // -------------------------------------------------------------------------

    /**
     * Generate a URL for a named route, substituting in parameters.
     *
     * Previously we looked up the route by index ($this->routes[$this->namedRoutes[$name]])
     * and then read $route['path']. Now we look up the Route object directly
     * and call $route->getPath() — cleaner and no index arithmetic needed.
     *
     *   $router->get('/courses/{id:\d+}/lessons/{lessonId:\d+}', '...')
     *          ->name('lesson.show');
     *
     *   $url = $router->route('lesson.show', ['id' => 5, 'lessonId' => 12]);
     *   // → '/courses/5/lessons/12'
     *
     * @throws \InvalidArgumentException if the name has not been registered
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException(
                "No route registered with name: '{$name}'. " .
                "Available names: " . implode(', ', array_keys($this->namedRoutes))
            );
        }

        // Retrieve the Route object — previously this was an array index lookup
        $route = $this->namedRoutes[$name];
        $path  = $route->getPath();

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


    // -------------------------------------------------------------------------
    // Dispatching
    // -------------------------------------------------------------------------

    /**
     * Match the incoming request to a Route and run it.
     *
     * The Router
     *
     *   1. Iterates over Route objects
     *   2. Calls $route->matchesPath() — to distinguish 404 from 405
     *   3. Calls $route->matches() — matching is the Route's own responsibility
     *   4. Calls $route->extractParams() — parameter extraction belongs to Route
     *   5. Passes control to runMiddleware() → callHandler()
     *
     * The Router no longer needs buildPattern() or extractParams() as private
     * methods — those now live on the Route class.
     */
    public function dispatch(Request $request, Response $response): void
    {
        $method = $request->getMethod();
        $path   = $request->getPath();

        // Collect HTTP methods that have a matching path (for 405 Allow header)
        $allowedMethods = [];

        $matches = [];
        foreach ($this->routes as $route) {
            // Does this route's path pattern match? (ignores HTTP method)
            if (!$route->matchesPath($path)) {
                continue;
            }

            // Path matches — record its method for a potential 405 response
            $allowedMethods[] = $route->getMethod();

            // Now check the full match (path + method)
            if (!$route->matches($method, $path, $matches)) {
                continue;
            }

            // Full match — extract named URL params and inject into Request
            $params = $route->extractParams($matches);
            $request->setRouteParams($params);

            // Run middleware pipeline → handler at the centre
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
            $this->handleMethodNotAllowed($allowedMethods);
        } else {
            $this->handleNotFound();
        }
    }


    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    private function addRoute(string $method, string $path, Closure|string $handler): Route
    {
        $group = end($this->groupStack) ?: ['prefix' => '', 'middleware' => []];

        $fullPath = $this->normalisePath($group['prefix'] . $path);

        $route = new Route(
            method:     $method,
            path:       $fullPath,
            handler:    $handler,
            middleware: $group['middleware']
        );

        $route->setNameCallback(function (string $name, Route $route): void {
            $this->namedRoutes[$name] = $route;
        });

        $this->routes[] = $route;

        return $route; // returned so callers can chain ->middleware() and ->name()
    }


    /**
     * Return the most recently registered Route object, or null if none exist.
     */
    private function lastRoute(): ?Route
    {
        $last = end($this->routes);
        return $last !== false ? $last : null;
    }


    /**
     * Execute the middleware pipeline using the "onion" pattern.
     */
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
     * Resolve and invoke the route handler.
     */
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

        $controllerClass = $this->controllerNamespace . $controllerName;

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException(
                "Controller class '{$controllerClass}' not found. " .
                "Check the class name and namespace in your route definition."
            );
        }

        // Pass the shared View instance as the first constructor argument.
        // Once the Container is built, replace this line with:
        //   $controller = $container->make($controllerClass);
        // and the Container will resolve View plus all other dependencies automatically.
        $controller = new $controllerClass($this->view);

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException(
                "Method '{$method}' does not exist on '{$controllerClass}'."
            );
        }

        $controller->$method($request, $response);
    }

    /**
     * Normalise a URL path:
     *   - Ensure it begins with /
     *   - Collapse any // double slashes (produced by group prefix concatenation)
     *   - Strip trailing slash (except for root /)
     */
    private function normalisePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/{2,}#', '/', $path);
        return $path === '/' ? $path : rtrim($path, '/');
    }

    /**
     * Send a 404 Not Found response.
     *
     * Delegates to View::renderError() — which looks for errors/404.php,
     * falls back to errors/generic.php, and finally falls back to inline HTML.
     * No raw strings here — all rendering goes through the View pipeline.
     */
    private function handleNotFound(): void
    {
        $this->view->renderError(404, 'The page you requested could not be found.');
    }

    /**
     * Send a 405 Method Not Allowed response.
     *
     * @param string[] $allowedMethods HTTP methods that ARE valid for this path
     */
    private function handleMethodNotAllowed(array $allowedMethods): void
    {
        header('Allow: ' . implode(', ', array_unique($allowedMethods)));
        $this->view->renderError(405, 'Method Not Allowed');
    }
}