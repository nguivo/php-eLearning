<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * InstructorMiddleware
 *
 * Ensures the authenticated user has the instructor or admin role.
 * Always runs AFTER AuthMiddleware — by the time this runs, we are
 * guaranteed to have a logged-in user.
 *
 * Admins are permitted through because they need full platform access,
 * including the ability to inspect and manage instructor content.
 *
 * If the role check fails:
 *   - JSON / AJAX requests → 403 JSON error
 *   - Browser requests     → redirect to student dashboard
 */
class InstructorMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        $role = $_SESSION['user_role'] ?? null;

        if ($role === 'instructor' || $role === 'admin') {
            $next();
            return;
        }

        if ($this->expectsJson($request)) {
            $this->jsonError($response, 'Access denied. Instructor role required.', 403);
            return;
        }

        $response->redirect('/student/dashboard');
    }
}