<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Request
 *
 * Wraps the incoming HTTP request. The router populates route params
 * after a successful match so controllers can read them via getRouteParam()
 * */

class Request
{
    private array $routeParams = [];

    /** Returns the HTTP method */
    public function getMethod(): string
    {
        // HTML forms only support GET/POST
        // we will use a hidden input named _method to simulate the other methods
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE', 'PATCH'])) {
                return $override;
            }
        }

        return $method;
    }


    /** Returns the URL path without query string, e.g., '/courses/48' */
    public function getPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return '/'.ltrim((string) $path, '/');
    }


    /** Returns a query string value: /courses?page=2 -> getQuery('page') = '2' */
    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }


    /** Returns a POST body value */
    public function getBody(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }


    /** Returns all post data (sanitized) */
    public function all(): array
    {
        return array_map('trim', $_POST);
    }


    /** Returns a URL parameter extracted by the Router, e.g., {id} */
    public function getRouteParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }


    /** Returns all URL parameters extracted by the Router */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }


    /** Called by the Router after a successful route match */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }


    /** Returns a value form either GET or POST */
    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }


    /** Check if a key exists in the request body */
    public function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }


    /** Returns the raw request body (useful for JSON API requests */
    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }


    /** Decodes a JSON request body into an array */
    public function json(): array
    {
        return json_decode($this->rawBody(), true) ?? [];
    }


    /** Returns a specific uploaded file from $_FILES */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }


    /** True if the request was made via AJAX (XMLHttpRequest) */
    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }


}












