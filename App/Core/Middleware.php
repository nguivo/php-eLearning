<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Middleware — Base class (actually an interface contract) for all middleware.
 *
 * USAGE
 * ─────
 * Every middleware in App/Middleware/ extends this class and implements handle():
 *
 *   class AuthMiddleware extends Middleware {
 *       public function handle(Request $req, Response $res, callable $next): void {
 *           if (!isset($_SESSION['user_id'])) {
 *               $res->redirect('/login');
 *               return;   // ← stops the chain
 *           }
 *           $next();      // ← passes control to the next middleware or handler
 *       }
 *   }
 */
abstract class Middleware
{
    /**
     * Process the request.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The outgoing HTTP response
     * @param callable $next     The next layer in the middleware pipeline
     */
    abstract public function handle(Request $request, Response $response, callable $next): void;


    protected function expectsJson(Request $request): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return $request->isAjax() || str_contains($accept, 'application/json');
    }

    /**
     * Send a JSON error response and stop execution.
     *
     * @param Response $response
     * @param string   $message    Human-readable error message
     * @param int      $statusCode HTTP status code (401, 403, 422, etc.)
     */
    protected function jsonError(Response $response, string $message, int $statusCode = 400): void
    {
        $response->setStatusCode($statusCode);
        $response->setHeader('Content-Type', 'application/json; charset=utf-8');
        $response->setBody(json_encode([
            'error'   => true,
            'message' => $message,
            'status'  => $statusCode,
        ]));
        $response->send();
        exit;
    }

    /**
     * Read the authenticated user ID from the session.
     * Returns null if no user is logged in.
     */
    protected function authId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $id = $_SESSION['user_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    /**
     * Check whether a user is currently logged in.
     */
    protected function isLoggedIn(): bool
    {
        return $this->authId() !== null;
    }
}