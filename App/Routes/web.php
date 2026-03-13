<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;

/**
 * Web Routes
 *
 * All application routes are defined here.
 * $router is injected by public/index.php
 * */

// --------------------------------
// PUBLIC ROUTES - no auth required
// --------------------------------

// get() returns a Route. ->name() calls Route::setName() and registers the
// name in the Router's $namedRoutes index. No change in written syntax.
$router->get('/', 'HomeController@index')->name('home');

$router->get('/courses', 'CourseController@index')->name('courses.index');

// {slug:[a-z0-9-]+} constrains the segment to URL-safe characters.
// The Route compiles this to a named capture group regex in its constructor.
$router->get('/courses/{slug:[a-z0-9-]+}', 'CourseController@show')->name('courses.show');

$router->get('/search', 'SearchController@index')->name('search');

$router->get('/about','PageController@about')->name('page.about');
$router->get('/contact', 'PageController@contact')->name('page.contact');
$router->post('/contact', 'PageController@sendMessage')->name('page.contact.send');


// =============================================================================
// AUTH ROUTES — Guests only (GuestMiddleware redirects logged-in users away)
// =============================================================================

// Inside a group, every registered route is constructed with the group's
// middleware already baked in. ->name() still chains on the Route object.
$router->group(['middleware' => [GuestMiddleware::class]], function ($router) {

    $router->get('/login',           'AuthController@showLogin')->name('auth.login');
    $router->post('/login',          'AuthController@login')->name('auth.login.submit');

    $router->get('/register',        'AuthController@showRegister')->name('auth.register');
    $router->post('/register',       'AuthController@register')->name('auth.register.submit');

    $router->get('/forgot-password', 'AuthController@showForgotPassword')->name('auth.forgot');
    $router->post('/forgot-password','AuthController@sendResetLink')->name('auth.forgot.submit');

    // {token:\w+} — alphanumeric only; keeps malformed tokens from reaching the controller
    $router->get('/reset-password/{token:\w+}',  'AuthController@showResetPassword')->name('auth.reset');
    $router->post('/reset-password/{token:\w+}', 'AuthController@resetPassword')->name('auth.reset.submit');

});

// Logout lives outside the guest group (only logged-in users can log out).
// ->middleware() calls Route::addMiddleware(), ->name() calls Route::setName().
// The written syntax is unchanged from before — only the receiving class differs.
$router->post('/logout', 'AuthController@logout')
    ->middleware([AuthMiddleware::class])
    ->name('auth.logout');



// =============================================================================
// STUDENT ROUTES — Authenticated students
// =============================================================================

// The group bakes AuthMiddleware into every Route constructed inside.
// Each ->name() call inside chains on the Route that get/post/etc just returned.
$router->group(['prefix' => '/student', 'middleware' => [AuthMiddleware::class]], function ($router) {

    $router->get('/dashboard',  'Student\DashboardController@index')->name('student.dashboard');
    $router->get('/my-courses', 'Student\EnrollmentController@index')->name('student.courses');

    $router->post('/enroll/{courseId:\d+}', 'Student\EnrollmentController@enroll')
        ->name('student.enroll');

    // Two typed params — both compiled into the Route's regex at construction time
    $router->get('/courses/{courseId:\d+}/lessons/{lessonId:\d+}', 'Student\LessonController@watch')
        ->name('student.lesson.watch');

    $router->post('/courses/{courseId:\d+}/lessons/{lessonId:\d+}/complete', 'Student\LessonController@markComplete')
        ->name('student.lesson.complete');

    $router->get('/certificates/{courseId:\d+}', 'Student\CertificateController@download')
        ->name('student.certificate');

    $router->get('/profile',  'Student\ProfileController@show')->name('student.profile');
    $router->post('/profile', 'Student\ProfileController@update')->name('student.profile.update');

});


// =============================================================================
// INSTRUCTOR ROUTES — Authenticated instructors
// =============================================================================

$router->group(['prefix' => '/instructor', 'middleware' => [AuthMiddleware::class, InstructorMiddleware::class]], function ($router) {

    $router->get('/dashboard', 'Instructor\DashboardController@index')->name('instructor.dashboard');

    // Course CRUD
    // Note: /courses/create must be registered BEFORE /courses/{id:\d+}/edit
    // so the literal segment 'create' is matched first and never captured as {id}.
    // The Route's \d+ constraint already prevents this, but ordering is good habit.
    $router->get('/courses',               'Instructor\CourseController@index')->name('instructor.courses');
    $router->get('/courses/create',        'Instructor\CourseController@create')->name('instructor.courses.create');
    $router->post('/courses',              'Instructor\CourseController@store')->name('instructor.courses.store');
    $router->get('/courses/{id:\d+}/edit', 'Instructor\CourseController@edit')->name('instructor.courses.edit');
    $router->put('/courses/{id:\d+}',      'Instructor\CourseController@update')->name('instructor.courses.update');
    $router->delete('/courses/{id:\d+}',   'Instructor\CourseController@destroy')->name('instructor.courses.destroy');

    // PATCH = partial update; only the published status field changes
    $router->patch('/courses/{id:\d+}/publish',   'Instructor\CourseController@publish')->name('instructor.courses.publish');
    $router->patch('/courses/{id:\d+}/unpublish', 'Instructor\CourseController@unpublish')->name('instructor.courses.unpublish');

    // Lessons — nested under course; courseId and id are independent typed params
    $router->get('/courses/{courseId:\d+}/lessons/create',        'Instructor\LessonController@create')->name('instructor.lessons.create');
    $router->post('/courses/{courseId:\d+}/lessons',              'Instructor\LessonController@store')->name('instructor.lessons.store');
    $router->get('/courses/{courseId:\d+}/lessons/{id:\d+}/edit', 'Instructor\LessonController@edit')->name('instructor.lessons.edit');
    $router->put('/courses/{courseId:\d+}/lessons/{id:\d+}',      'Instructor\LessonController@update')->name('instructor.lessons.update');
    $router->delete('/courses/{courseId:\d+}/lessons/{id:\d+}',   'Instructor\LessonController@destroy')->name('instructor.lessons.destroy');

    $router->get('/earnings',  'Instructor\EarningsController@index')->name('instructor.earnings');
    $router->get('/analytics', 'Instructor\AnalyticsController@index')->name('instructor.analytics');

});


// =============================================================================
// ADMIN ROUTES — Platform administrators only
// =============================================================================

$router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class, AdminMiddleware::class]], function ($router) {

    $router->get('/dashboard', 'Admin\DashboardController@index')->name('admin.dashboard');

    // User management
    $router->get('/users',                   'Admin\UserController@index')->name('admin.users');
    $router->get('/users/{id:\d+}',          'Admin\UserController@show')->name('admin.users.show');
    $router->patch('/users/{id:\d+}/ban',    'Admin\UserController@ban')->name('admin.users.ban');
    $router->patch('/users/{id:\d+}/unban',  'Admin\UserController@unban')->name('admin.users.unban');

    // Course moderation
    $router->get('/courses',                    'Admin\CourseController@index')->name('admin.courses');
    $router->patch('/courses/{id:\d+}/approve', 'Admin\CourseController@approve')->name('admin.courses.approve');
    $router->patch('/courses/{id:\d+}/reject',  'Admin\CourseController@reject')->name('admin.courses.reject');

    // Payout management
    $router->get('/payouts',                       'Admin\PayoutController@index')->name('admin.payouts');
    $router->post('/payouts/{id:\d+}/process',     'Admin\PayoutController@process')->name('admin.payouts.process');

});


// =============================================================================
// API ROUTES — JSON responses for AJAX / mobile clients
// =============================================================================

$router->group(['prefix' => '/api/v1'], function ($router) {

    // Public endpoints — no auth needed
    $router->get('/courses',          'Api\CourseController@index')->name('api.courses.index');
    $router->get('/courses/{id:\d+}', 'Api\CourseController@show')->name('api.courses.show');

    // Protected endpoints — AuthMiddleware is merged with the outer group (none here)
    // and baked into each Route object at construction time
    $router->group(['middleware' => [AuthMiddleware::class]], function ($router) {
        $router->get('/me',          'Api\UserController@me')->name('api.me');
        $router->get('/enrollments', 'Api\EnrollmentController@index')->name('api.enrollments');
    });

});


// =============================================================================
// CLOSURE ROUTES
//
// The Closure is stored directly on the Route object as its $handler.
// ->name() chains on the Route exactly as it does for string handlers.
// =============================================================================

$router->get('/ping', function ($request, $response) {
    $response->json(['status' => 'ok', 'timestamp' => time()]);
})->name('ping');


// =============================================================================
// URL GENERATION — call route() on the Router (not on a Route object)
//
// The Router's $namedRoutes index maps name → Route object.
// route() calls Route::getPath() to retrieve the raw path, then substitutes params.
//
//   $router->route('home')
//   → '/'
//
//   $router->route('courses.show', ['slug' => 'intro-to-php'])
//   → '/courses/intro-to-php'
//
//   $router->route('student.lesson.watch', ['courseId' => 3, 'lessonId' => 7])
//   → '/student/courses/3/lessons/7'
//
//   $router->route('instructor.courses.edit', ['id' => 12])
//   → '/instructor/courses/12/edit'
//
//   $router->route('api.courses.show', ['id' => 99])
//   → '/api/v1/courses/99'
// =============================================================================

























