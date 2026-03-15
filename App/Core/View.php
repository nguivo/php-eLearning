<?php

declare(strict_types=1);

/**
 * View - the single rendering engine for the entire application
 * */
namespace App\Core;

class View
{
    private string $viewsPath;
    private string $defaultLayout;

    /**
     * Data shared across ALL views for the lifetime of this request.
     *
     * Set once via share() and automatically merged into every render call
     * Used for things every view needs: app name, authenticated user,
     * flash messages, CSRF token, etc.
     * */
    private array $globals = [];


    public function __construct(
        string $viewsPath,
        string $defaultLayout = 'layouts/main'
    )
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
        $this->defaultLayout = $defaultLayout;
    }


    /**
     * Global / Shared Data
     *
     * Usage:
     *      $view->share('appName', 'eLearning Platform')
     *      $view->share('currentUser', $authService->user())
     *      $view->share('csrfToken', $csrf->token())
     * */
    public function share(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }


    /**
     * Share multiple key-value pairs at once
     * */
    public function shareMany(array $data): void
    {
        $this->globals = array_merge($this->globals, $data);
    }


    /* Return all currently shared global variables */
    public function getGlobals(): array
    {
        return $this->globals;
    }


    // --------------------------------------------------
    // Core Rendering
    // --------------------------------------------------

    /**
     * Render a view file and return its output as a string
     *
     * it does not wrap the output in a layout, but returns it as-is
     *
     * Global variables are merged with data automatically
     * Data passed directly takes precedence over globals
     *
     * */
    public function render(string $view, array $data = []): string
    {
        $path = $this->resolvePath($view);

        // Globals first, then $data - $data wins on key collisions
        $variables = array_merge($this->globals, $data);

        return $this->renderFile($path, $variables);
    }


    /*
     * Render a view wrapped inside a layout
     * */
    public function renderWithLayout(
        string $view,
        array $data = [],
        ?string $layout = null
    ): string
    {
        $layoutFile = $layout ?? $this->defaultLayout;

        $data['content'] = $this->render($view, $data);

        return $this->render($layoutFile, $data);
    }


    /* Render a view and echo it directly to the browser */
    public function display(string $view, array $data = [], ?string $layout = null): void
    {
        echo $this->renderWithLayout($view, $data, $layout);
    }


    /* Error Page rendering */
    public function renderError(int $statusCode, string $message = ''): void
    {
        http_response_code($statusCode);

        $defaultMessages = [
            400 => 'Bad Request',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Content',
            500 => 'Internal Server Error',
        ];

        $message = $message ?: ($defaultMessages[$statusCode] ?? 'An error occurred');

        $data = [
            'statusCode' => $statusCode,
            'message' => $message,
        ];

        // Try to render a proper error view with layout: errors/404.php, errors/403.php
        $errorView = "errors/{$statusCode}";
        try {
            echo $this->renderWithLayout($errorView, $data);
        } catch (\RuntimeException) {
            try {
                echo $this->renderWithLayout('errors/generic', $data);
            } catch (\RuntimeException) {
                echo $this->fallbackErrorPage($statusCode, $message);
            }
        }
    }


    /* Check whether a view file exists without throwing an error */
    public function exists(string $view): bool
    {
        try {
            $this->resolvePath($view);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }


    public function getPath(string $view): string
    {
        return $this->resolvePath($view);
    }


    // ---------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------

    /*
     * Resolve a view name to its absolute filesystem path
     * */
    public function resolvePath(string $view): string
    {
        $relative = str_replace('.', '/', $view);
        $relative = ltrim($relative, '/');
        $fullPath = $this->viewsPath. DIRECTORY_SEPARATOR.$relative.'.php';
        if (!file_exists($fullPath)) {
            throw new \RuntimeException(
                "View [{$view}] not found. Expected file at: {$fullPath}"
            );
        }

        return $fullPath;
    }


    /**
     * Render a resolved file path with extracted variables.
     *
     * this is the only place in the entire codebase that calls
     * extract(), ob_start(), and require on a view file.
     * Everything else delegates here.
     * */
    private function renderFile(string $filePath, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();

        // require inside a try-catch so output buffer is always cleaned
        // even if the view file throws an exception mid-render
        try {
            require $filePath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }


    /**
     * Generate a minimal inline HTML error page.
     *
     * Only used when no error view files exist at all —
     * e.g. on a fresh installation before any views have been created.
     */
    private function fallbackErrorPage(int $statusCode, string $message): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>{$statusCode} — {$message}</title>
            <style>
                body { font-family: sans-serif; text-align: center; padding: 4rem; color: #333; }
                h1   { font-size: 4rem; margin-bottom: 0.5rem; }
                p    { font-size: 1.25rem; color: #666; }
            </style>
        </head>
        <body>
            <h1>{$statusCode}</h1>
            <p>{$message}</p>
        </body>
        </html>
        HTML;
    }


}





















