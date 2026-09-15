<?php

declare(strict_types=1);

/**
 * API route definitions.
 *
 * Returns a configured Router. Route handlers are referenced as
 * [Controller::class, 'method'] and resolved lazily by the router.
 */

use Iterp\Core\Auth;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Router;

$router = new Router();

// Simple service health check.
$router->get('/api/health', function (Request $request) {
    $mysql = false;
    try {
        \Iterp\Core\Database::pdo()->query('SELECT 1')->fetch();
        $mysql = true;
    } catch (Throwable $e) {
        // connection failure - reported in the payload below.
    }

    return Response::success([
        'status' => 'ok',
        'time'   => date('c'),
        'php'    => PHP_VERSION,
        'mysql'  => $mysql,
    ]);
});

// ---------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------
$auth      = \Iterp\Controllers\AuthController::class;
$authOnly  = [[Auth::class, 'middleware']];

$router->post('/api/auth/register',            [$auth, 'register']);
$router->post('/api/auth/login',               [$auth, 'login']);
$router->post('/api/auth/logout',              [$auth, 'logout'], $authOnly);
$router->get('/api/auth/me',                   [$auth, 'me'], $authOnly);
$router->get('/api/auth/email/verify/{token}', [$auth, 'verifyEmail']);
$router->post('/api/auth/email/resend',        [$auth, 'resendVerification'], $authOnly);
$router->post('/api/auth/password/forgot',     [$auth, 'forgotPassword']);
$router->post('/api/auth/password/reset',      [$auth, 'resetPassword']);

// ---------------------------------------------------------------------
// Master Settings + Annual Settings + SIS resource CRUD APIs.
// Every resource is backed by an existing migration and protected by auth.
// ---------------------------------------------------------------------
$locale   = 'Iterp\\Controllers\\';
$authOnly = [[Auth::class, 'middleware']];

// Resolve the current tenant from the request's Host header domain. Public (no
// auth) because the tenant is derived from the domain — not the user's auth
// state — and the client needs it before login. Returns 404 when the domain
// doesn't match any tenant.
$router->get('/api/tenant/resolve', [$locale . 'TenantController', 'resolveByDomain']);

$resources = [
    // Master Settings > Organizations
    'firms'                  => 'FirmController',
    // Master Settings > Location
    'countries'              => 'CountryController',
    'states'                 => 'StateController',
    'cities'                => 'CityController',
    'localities'             => 'LocalityController',
    'tenants'                => 'TenantController',
    // Master Settings > Title
    'titles'                 => 'TitleController',
    // Annual Settings
    'academic-years'         => 'AcademicYearController',
    'school-calendar'        => 'EventController',
    'custom-field-categories' => 'CustomFieldCategoryController',
    'custom-fields'          => 'CustomFieldController',
    'attendance-legends'     => 'AttendanceLegendController',
    'documents'              => 'DocumentController',
    // SIS
    'classes'                => 'AcademicClassController',
    'sections'               => 'SectionController',
    'groups'                 => 'GroupController',
    'student-categories'     => 'StudentCategoryController',
    'students'               => 'StudentController',
    // Roles & Employees
    'roles'                  => 'RoleController',
    'employees'              => 'EmployeeController',
    // Admission
    'prospectus'             => 'ProspectusController',
    'registrations'          => 'RegistrationController',
];

// Admission custom routes (registered before resource loop to avoid {id} capturing)
$router->get('/api/prospectus/next-number',               [$locale . 'ProspectusController', 'nextNumber'], $authOnly);
$router->get('/api/registrations/next-number',            [$locale . 'RegistrationController', 'nextNumber'], $authOnly);
$router->post('/api/registrations/{id}/convert-to-student', [$locale . 'RegistrationController', 'convertToStudent'], $authOnly);
$router->get('/api/admission/reports/collection',         [$locale . 'AdmissionReportController', 'collectionReport'], $authOnly);
$router->get('/api/admission/reports/analysis',           [$locale . 'AdmissionReportController', 'analysisReport'], $authOnly);
$router->get('/api/admission/reports/strength',           [$locale . 'AdmissionReportController', 'strengthReport'], $authOnly);

foreach ($resources as $path => $controllerName) {
    $controller = $locale . $controllerName;

    $router->get('/api/' . $path,              [$controller, 'index'],  $authOnly);
    $router->post('/api/' . $path,              [$controller, 'store'],  $authOnly);
    $router->get('/api/' . $path . '/{id}',      [$controller, 'show'],   $authOnly);
    $router->put('/api/' . $path . '/{id}',      [$controller, 'update'], $authOnly);
    $router->patch('/api/' . $path . '/{id}',    [$controller, 'update'], $authOnly);
    $router->delete('/api/' . $path . '/{id}',   [$controller, 'destroy'], $authOnly);
}

return $router;