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
    // Fees
    'fee-heads'              => 'FeeHeadController',
    'fee-bill-schemes'       => 'FeeBillSchemeController',
    'fee-bill-scheme-slabs'  => 'FeeBillSchemeSlabController',
    'fee-bill-scheme-amounts'=> 'FeeBillSchemeAmountController',
    'fee-concessions'        => 'FeeConcessionController',
    'concessions'            => 'FeeConcessionController',
    'fee-collections'        => 'FeeCollectionController',
    // Examination Module
    'exam-types'             => 'ExamTypeController',
    'exam-subjects'          => 'ExamSubjectController',
    'exams'                  => 'ExamController',
    'exam-schedules'         => 'ExamScheduleController',
];

// Examination Module custom routes
$router->get('/api/exams/{id}/allocations',                [$locale . 'ExamController', 'getAllocations'], $authOnly);
$router->get('/api/exams/{id}/allocated-classes',          [$locale . 'ExamController', 'getAllocatedClasses'], $authOnly);
$router->post('/api/exams/{id}/allocations',               [$locale . 'ExamController', 'saveAllocations'], $authOnly);
$router->post('/api/exam-schedules/batch-save',             [$locale . 'ExamScheduleController', 'batchSave'], $authOnly);
$router->get('/api/exam-marks/entry-sheet',                [$locale . 'ExamMarkController', 'entrySheet'], $authOnly);
$router->post('/api/exam-marks/batch-save',                [$locale . 'ExamMarkController', 'batchSave'], $authOnly);
$router->get('/api/exam-marks/result-sheet',               [$locale . 'ExamMarkController', 'resultSheet'], $authOnly);
$router->get('/api/exam-marks/marksheet-data',             [$locale . 'ExamMarkController', 'marksheetData'], $authOnly);

// ID Card Module custom routes
$router->get('/api/id-cards/students',                     [$locale . 'IdCardController', 'students'], $authOnly);
$router->get('/api/id-cards/teachers',                     [$locale . 'IdCardController', 'teachers'], $authOnly);

// Attendance Module routes (Student & Employee)
$router->get('/api/student-attendance/sheet',              [$locale . 'AttendanceController', 'studentSheet'], $authOnly);
$router->post('/api/student-attendance/batch-save',        [$locale . 'AttendanceController', 'studentBatchSave'], $authOnly);
$router->get('/api/student-attendance/report',             [$locale . 'AttendanceController', 'studentReport'], $authOnly);
$router->get('/api/employee-attendance/sheet',             [$locale . 'AttendanceController', 'employeeSheet'], $authOnly);
$router->post('/api/employee-attendance/batch-save',       [$locale . 'AttendanceController', 'employeeBatchSave'], $authOnly);
$router->get('/api/employee-attendance/report',            [$locale . 'AttendanceController', 'employeeReport'], $authOnly);

// Report Format Settings routes
$router->get('/api/report-format-settings',                [$locale . 'ReportFormatSettingController', 'index'], $authOnly);
$router->post('/api/report-format-settings',               [$locale . 'ReportFormatSettingController', 'save'], $authOnly);
$router->put('/api/report-format-settings',                [$locale . 'ReportFormatSettingController', 'save'], $authOnly);

// Fee collection custom routes (registered before resource loop to avoid {id} capturing)
$router->get('/api/fee-collections/next-number',          [$locale . 'FeeCollectionController', 'nextNumber'], $authOnly);
$router->get('/api/fee-collections/student-dues',         [$locale . 'FeeCollectionController', 'studentDues'], $authOnly);
$router->get('/api/fee-collections/due-report',           [$locale . 'FeeCollectionController', 'dueReport'], $authOnly);

// Fee bill scheme assignments custom routes
$router->get('/api/fee-bill-schemes/{id}/assignments',     [$locale . 'FeeBillSchemeController', 'getAssignments'], $authOnly);
$router->post('/api/fee-bill-schemes/{id}/assign-sections', [$locale . 'FeeBillSchemeController', 'assignSections'], $authOnly);
$router->post('/api/fee-bill-schemes/{id}/assign-students', [$locale . 'FeeBillSchemeController', 'assignStudents'], $authOnly);

// Admission custom routes (registered before resource loop to avoid {id} capturing)
$router->get('/api/prospectus/next-number',               [$locale . 'ProspectusController', 'nextNumber'], $authOnly);
$router->get('/api/registrations/next-number',            [$locale . 'RegistrationController', 'nextNumber'], $authOnly);
$router->post('/api/registrations/{id}/convert-to-student', [$locale . 'RegistrationController', 'convertToStudent'], $authOnly);
$router->get('/api/admission/reports/collection',         [$locale . 'AdmissionReportController', 'collectionReport'], $authOnly);
$router->get('/api/admission/reports/analysis',           [$locale . 'AdmissionReportController', 'analysisReport'], $authOnly);
$router->get('/api/admission/reports/strength',           [$locale . 'AdmissionReportController', 'strengthReport'], $authOnly);

// Student Import & Fast Edit routes
$router->get('/api/students/import-template',              [$locale . 'StudentImportController', 'downloadTemplate'], $authOnly);
$router->post('/api/students/import',                     [$locale . 'StudentImportController', 'import'], $authOnly);
$router->get('/api/students/fast-edit-data',              [$locale . 'StudentController', 'fastEditData'], $authOnly);
$router->post('/api/students/bulk-update',                [$locale . 'StudentController', 'bulkUpdate'], $authOnly);

foreach ($resources as $path => $controllerName) {
    $controller = $locale . $controllerName;

    $router->post('/api/' . $path . '/reorder',    [$controller, 'reorder'], $authOnly);
    $router->get('/api/' . $path,                  [$controller, 'index'],  $authOnly);
    $router->post('/api/' . $path,                 [$controller, 'store'],  $authOnly);
    $router->get('/api/' . $path . '/{id}',      [$controller, 'show'],   $authOnly);
    $router->put('/api/' . $path . '/{id}',      [$controller, 'update'], $authOnly);
    $router->patch('/api/' . $path . '/{id}',    [$controller, 'update'], $authOnly);
    $router->delete('/api/' . $path . '/{id}',   [$controller, 'destroy'], $authOnly);
}

return $router;