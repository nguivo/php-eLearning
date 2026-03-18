<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * AuthMiddleware
 *
 * Protects routes that require an authenticated user.
 * Applied to all student, instructor, and admin route groups.
 *
 * If the request expects JSON (API / AJAX) → returns a 401 JSON error.
 * Otherwise → redirects to the login page.
 *
 * The intended destination is stored in the session so the login
 * controller can redirect back after a successful login.
 */
class AuthMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        if ($this->isLoggedIn()) {
            $next();
            return;
        }

        if ($this->expectsJson($request)) {
            $this->jsonError($response, 'Unauthenticated. Please log in.', 401);
            return;
        }

        // Store the intended URL so the login controller can redirect back
        $_SESSION['intended_url'] = $request->getPath();

        $response->redirect('/login');
    }
}