<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

class Route
{
    private string $method;
    private string $path;
    private Closure|string $handler;
    private array $middleware = [];

    /*
     * Optional human-readable name used for URL generation
     * e.g. 'courses.show', 'auth.login'
     * Null until ->name() is called on the Router
     * */
    private ?string $name = null;

    /*
     * The compile regex pattern build from $path
     * Stored here, so it is only computed once per route, not on every request.
     * e.g. '#^/courses/(?P<id>\d+)/lessons/(?P<lessonId>\d+)$#'
     * */
    private string $compiledPattern;
    private mixed $nameCallback = null;


    public function __construct(
        string  $method,
        string  $path,
        Closure|string  $handler,
        array   $middleware = []
    ) {
        $this->method       = strtoupper($method);
        $this->path         = $path;
        $this->handler      = $handler;
        $this->middleware   = $middleware;
        $this->compiledPattern  = $this->compilePattern($path);
    }


    public function middleware(array $middlewareList): self
    {
        $this->middleware = array_merge($this->middleware, $middlewareList);
        return $this;
    }


    /** Name this route for URL generation - called by /Routes/web.php */
    public function name(string $name): self
    {
        $this->name = $name;

        if ($this->nameCallback !== null) {
            ($this->nameCallback)($name, $this);
        }

        return $this;
    }

    // --------------------------------------------------------------
    // Mutation Methods - called by the Router for fluent chaining
    // --------------------------------------------------------------

    /**
     * Register the callback the Router provides for name registration.
     *
     * Called by Router::addRoute() immediately after the Route is created.
     * When name() is later called on this Route, the callback fires and the
     * Router registers this Route in its $namedRoutes index.
     *
     * @param callable(string, Route): void $callback
     */
    public function setNameCallback(callable $callback): void
    {
        $this->nameCallback = $callback;
    }


    /**
     * Internal alias for middleware() — used by the Router when it needs
     * to append group-inherited middleware without going through the public
     * fluent API (e.g. inside Router::middleware() fallback method).
     */
    public function addMiddleware(array $middlewareList): self
    {
        return $this->middleware($middlewareList);
    }


    // --------------------------------------------------------------------
    // Matching - called by Router::dispatch()
    // --------------------------------------------------------------------
    public function matches(string $method, string $path, array &$matches): bool
    {
        if ($this->method !== strtoupper($method)) {
            return false;
        }

        return (bool) preg_match($this->compiledPattern, $path, $matches);
    }


    /**
     * Test whether the path alone matches, regardless of HTTP method
     *
     * The Router uses this to distinguish a 404 (path not found at all)
     * from a 405 (path found but wrong method), so it can send the correct
     * status code and 'Allow' header.
     * */
    public function matchesPath(string $path): bool
    {
        return (bool) preg_match($this->compiledPattern, $path);
    }


    /**
     * Extract named URL parameters from a preg_match result.
     *
     * preg_match fills $matches with both numeric keys (0,1,2,..) and the
     * named capture group keys we defined. We only want the named ones.
     *
     * For route /courses/{id:\d+}/lessons/{lessonId:\d+} matching
     * /courses/5/lessons/12, this returns: ['id' => '5', 'lessonId' => '12']
     * */
    public function extractParams(array $matches): array
    {
        // Gather the declared param names from the raw path definition
        preg_match_all('#\{(\w+)[^}]*\}#', $this->path, $paramNames);

        $params = [];
        foreach ($paramNames[1] as $name) {
            // strip trailing '?' from optional params
            $cleanName = rtrim($name, '?');
            if (isset($matches[$cleanName]) && $matches[$cleanName] !== '') {
                $params[$cleanName] = $matches[$cleanName];
            }
        }

        return $params;
    }


    // ------------------------------------------------------------
    // Getters
    // ------------------------------------------------------------
    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return Closure|string
     */
    public function getHandler(): Closure|string
    {
        return $this->handler;
    }

    /**
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCompiledPattern(): string
    {
        return $this->compiledPattern;
    }

    public function hasName(): bool
    {
        return $this->name !== null;
    }


    // -------------------------------------------------------------
    // Private - Pattern Compilation
    // -------------------------------------------------------------

    /**
     * Compile the raw path string into a named-capture-group regex.
     *
     * This runs once in the constructor and the result is cached in
     * $this->compiledPattern, so repeated dispatch calls never recompile.
     *
     * Placeholder formats supported:
     *
     *  {id}        required, any non-slash value   -> (?P<id>[^\/]+)
     *  {id:\d+}    required, digits only           -> (?P<id>\d+)
     *  {slug:[a-z0-9-]+} required, slug pattern -> (?P<slug>[a-z0-9-]+)
     *  {path:.*}   required, anything incl. slashes -> (?P<path>.*)
     *  {page?}     optional, any non-slash value   -> (?P<page>[^\/]+)?
     * */
    private function compilePattern(string $path): string
    {
        // 1. {param:custom-regex} - must come before the plain {param} rule
        // because both patterns would match the same opening brace
        $pattern = preg_replace(
            '#\{(\w+):([^}]+)\}#',
            '(?P<$1>$2',
            $path
        );

        // 2. {param?} - optional, no custom regex constraint
        $pattern = preg_replace(
            '#\{(\w+)\?\}#',
            '(?P<$1>[^\/]+)?',
            $pattern
        );

        // 3. {param} - required, no custom regex constraint
        $pattern = preg_replace(
            '#\{(w+)\}#',
            '(?P<$1>[^\/]+)',
            $pattern
        );

        return '#^'.$pattern.'$#';
    }


}


















