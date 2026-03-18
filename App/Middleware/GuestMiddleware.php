<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * GuestMiddleware
 *
 * Protects routes that should only be accessible to unauthenticated users —
 * login, register, forgot password, and password reset pages.
 *
 * If an already-logged-in user tries to visit /login or /register,
 * they are redirected to their appropriate dashboard based on their role.
 * There is no reason for an authenticated user to see the login page.
 */
class GuestMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        if (!$this->isLoggedIn()) {
            $next();
            return;
        }

        // User is already authenticated — redirect to their dashboard
        $role = $_SESSION['user_role'] ?? 'student';

        $dashboard = match ($role) {
            'admin'      => '/admin/dashboard',
            'instructor' => '/instructor/dashboard',
            default      => '/student/dashboard',
        };

        $response->redirect($dashboard);
    }
}