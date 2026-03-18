<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * AdminMiddleware
 *
 * Ensures the authenticated user has the admin role.
 * Always runs AFTER AuthMiddleware — by the time this runs, we are
 * guaranteed to have a logged-in user.
 *
 * Unlike InstructorMiddleware, this is strict — only 'admin' is permitted.
 * Instructors cannot access admin routes.
 *
 * If the role check fails:
 *   - JSON / AJAX requests → 403 JSON error
 *   - Browser requests     → redirect to student dashboard
 */
class AdminMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        $role = $_SESSION['user_role'] ?? null;

        if ($role === 'admin') {
            $next();
            return;
        }

        if ($this->expectsJson($request)) {
            $this->jsonError($response, 'Access denied. Admin role required.', 403);
            return;
        }

        $response->redirect('/student/dashboard');
    }
}