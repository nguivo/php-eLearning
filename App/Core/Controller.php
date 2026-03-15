<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controller — Base class for all application controllers.
 */
abstract class Controller
{
    protected View $view;

    /**
     * Receive the shared View instance.
     * @param View $view Injected by the Container (singleton)
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }


    /* Renders a view without a layout */
    protected function render(string $view, array $data = []): string
    {
        return $this->view->render($view, $data);
    }

    /**
     * Render a view inside a layout and send the output to the browser.
     *
     * @param string               $template  The inner view file
     * @param array<string, mixed> $data      Variables for both view and layout
     * @param string|null          $layout    Layout override (null = use default)
     */
    protected function view(string $template, array $data = [], ?string $layout = null): void
    {
        echo $this->view->renderWithLayout($template, $data, $layout);
    }


    /**
     * Send a JSON response and stop execution.
     *
     * Use for API endpoints and AJAX responses.
     * Always write this as a return statement even though it exits —
     * the return keyword makes the intent clear to readers.
     *
     * Usage:
     *   return $this->json(['course' => $course]);
     *   return $this->json(['error' => 'Not found'], 404);
     *   return $this->json($course->toArray(), 201);
     *
     * @param mixed $data       Anything JSON-serialisable (array, object, string, etc.)
     * @param int   $statusCode HTTP status code (default 200)
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }


    /**
     * Redirect to a URL and stop execution.
     *
     * @param string $url        Destination URL (relative or absolute)
     * @param int    $statusCode 302 for temporary, 301 for permanent
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect to the previous page using the HTTP Referer header.
     *
     * @param string $fallback URL to redirect to if HTTP_REFERER is not set
     */
    protected function redirectBack(string $fallback = '/'): void
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? $fallback);
    }


    // -------------------------------------------------------------------------
    // Flash Messages
    // -------------------------------------------------------------------------

    /**
     * Store a one-time message in the session to display on the next request.
     *
     * @param string $type    'success' | 'error' | 'warning' | 'info'
     * @param string $message The message text
     */
    protected function flash(string $type, string $message): void
    {
        $this->startSessionIfNeeded();
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * Read and immediately delete a flash message.
     * @param string $type 'success' | 'error' | 'warning' | 'info'
     */
    public static function getFlash(string $type): ?string
    {
        if (!isset($_SESSION['_flash'][$type])) {
            return null;
        }
        $message = $_SESSION['_flash'][$type];
        unset($_SESSION['_flash'][$type]);
        return $message;
    }


    // -------------------------------------------------------------------------
    // Authentication Helpers
    // -------------------------------------------------------------------------

    protected function authId(): ?int
    {
        $this->startSessionIfNeeded();
        $id = $_SESSION['user_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }


    protected function isLoggedIn(): bool
    {
        return $this->authId() !== null;
    }


    protected function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $this->flash('error', 'You must be logged in to access this page.');
            $this->redirect('/login');
        }
    }


    // -------------------------------------------------------------------------
    // Input Helpers
    // -------------------------------------------------------------------------

    /**
     * Read a value from POST or GET and sanitise it.
     *
     * Trims whitespace and strips HTML tags by default.
     * Pass $sanitise = false for rich-text fields (description, body copy, etc.)
     * where HTML is intentional.
     *
     * Usage:
     *   $title       = $this->input('title');
     *   $description = $this->input('description', '', sanitise: false);
     *
     * @param string $key      Field name
     * @param mixed  $default  Fallback if the field is absent
     * @param bool   $sanitise Strip HTML tags? (default true)
     */
    protected function input(string $key, mixed $default = null, bool $sanitise = true): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        if (is_string($value)) {
            $value = trim($value);
            if ($sanitise) {
                $value = strip_tags($value);
            }
        }

        return $value;
    }

    /**
     * Return all POST data as a sanitised associative array.
     * Pass the whole thing to a Service or Validator.
     *
     * Usage:
     *   $data = $this->all();
     *   $courseService->create($data);
     *
     * @param bool $sanitise Strip HTML tags from string values? (default true)
     * @return array<string, mixed>
     */
    protected function all(bool $sanitise = true): array
    {
        return array_map(function ($value) use ($sanitise) {
            if (!is_string($value)) {
                return $value;
            }
            $value = trim($value);
            return $sanitise ? strip_tags($value) : $value;
        }, $_POST);
    }

    /**
     * Return true only if all listed fields are present and non-empty.
     *
     * Usage:
     *   if (!$this->has('title', 'price')) {
     *       return $this->json(['error' => 'Missing required fields'], 422);
     *   }
     */
    protected function has(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (empty($_POST[$key]) && empty($_GET[$key])) {
                return false;
            }
        }
        return true;
    }


    // -------------------------------------------------------------------------
    // HTTP Abort Helpers — delegate to View::renderError()
    // -------------------------------------------------------------------------

    protected function abort404(string $message = 'Not Found'): void
    {
        $this->view->renderError(404, $message);
        exit;
    }


    protected function abort403(string $message = 'Forbidden'): void
    {
        $this->view->renderError(403, $message);
        exit;
    }


    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    private function startSessionIfNeeded(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}