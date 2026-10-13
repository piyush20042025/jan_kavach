<?php
/**
 * JAN KAVACH - Web Routes
 *
 * $router is supplied by JanKavach\Core\App.
 *
 * Middleware conventions:
 *   auth        - a valid session is required
 *   password    - forces a pending password change before anything else
 *   csrf        - verifies the CSRF token on POST/PUT/PATCH/DELETE
 *   permission: - one or more permission codes ("any of" when comma-separated)
 */

use JanKavach\Controllers\ApplicationController;
use JanKavach\Controllers\AuditController;
use JanKavach\Controllers\AuthController;
use JanKavach\Controllers\BeneficiaryController;
use JanKavach\Controllers\DashboardController;
use JanKavach\Controllers\DocumentController;
use JanKavach\Controllers\FieldDataController;
use JanKavach\Controllers\FollowupController;
use JanKavach\Controllers\GeoController;
use JanKavach\Controllers\MasterController;
use JanKavach\Controllers\MatchingController;
use JanKavach\Controllers\NotificationController;
use JanKavach\Controllers\ProfileController;
use JanKavach\Controllers\ReportController;
use JanKavach\Controllers\RoleController;
use JanKavach\Controllers\SchemeController;
use JanKavach\Controllers\SchemeImportController;
use JanKavach\Controllers\SettingsController;
use JanKavach\Controllers\UserController;

/** @var JanKavach\Core\Router $router */

// =====================================================================
// PUBLIC / GUEST
// =====================================================================

$router->get('/',       [AuthController::class, 'root']);
$router->get('/login',  [AuthController::class, 'showLogin'])->middleware('guest')->name('login');
$router->post('/login', [AuthController::class, 'login'])->middleware('guest', 'csrf');

$router->get('/forgot-password',   [AuthController::class, 'showForgotPassword'])->middleware('guest');
$router->post('/forgot-password',  [AuthController::class, 'sendResetLink'])->middleware('guest', 'csrf');
$router->get('/reset-password/{token}',  [AuthController::class, 'showResetPassword'])->middleware('guest');
$router->post('/reset-password',   [AuthController::class, 'resetPassword'])->middleware('guest', 'csrf');

$router->match(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])->middleware('auth');

// =====================================================================
// AUTHENTICATED AREA
// =====================================================================

$router->group(['middleware' => ['auth', 'password']], function ($router) {

    // -----------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------
    $router->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    $router->get('/dashboard/charts', [DashboardController::class, 'charts']);
    $router->get('/dashboard/state/{id}', [DashboardController::class, 'state'])
           ->middleware('permission:dashboard.state,dashboard.national');
    $router->get('/dashboard/district/{id}', [DashboardController::class, 'district']);

    // Global scheme search (section 34)
    $router->get('/search', [SchemeController::class, 'globalSearch'])->name('search');

    // -----------------------------------------------------------------
    // Scheme management (sections 6-15)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'schemes'], function ($router) {
        $router->get('/',            [SchemeController::class, 'index'])->middleware('permission:scheme.view')->name('schemes.index');
        $router->get('/create',      [SchemeController::class, 'create'])->middleware('permission:scheme.create');
        $router->post('/',           [SchemeController::class, 'store'])->middleware('permission:scheme.create', 'csrf');
        $router->get('/export',      [SchemeController::class, 'export'])->middleware('permission:scheme.export');
        $router->get('/print',       [SchemeController::class, 'printList'])->middleware('permission:scheme.view');
        $router->post('/check-duplicate', [SchemeController::class, 'checkDuplicate'])->middleware('permission:scheme.create', 'csrf');

        $router->get('/{id}',        [SchemeController::class, 'show'])->middleware('permission:scheme.view');
        $router->get('/{id}/edit',   [SchemeController::class, 'edit'])->middleware('permission:scheme.edit');
        $router->post('/{id}',       [SchemeController::class, 'update'])->middleware('permission:scheme.edit', 'csrf');
        $router->post('/{id}/delete',[SchemeController::class, 'destroy'])->middleware('permission:scheme.delete', 'csrf');
        $router->post('/{id}/toggle',[SchemeController::class, 'toggleStatus'])->middleware('permission:scheme.edit', 'csrf');
        $router->post('/{id}/copy',  [SchemeController::class, 'copy'])->middleware('permission:scheme.create', 'csrf');
        $router->get('/{id}/print',  [SchemeController::class, 'printOne'])->middleware('permission:scheme.view');

        // Eligibility rule builder (section 9)
        $router->get('/{id}/rules',        [SchemeController::class, 'rules'])->middleware('permission:scheme.rules');
        $router->post('/{id}/rules',       [SchemeController::class, 'saveRules'])->middleware('permission:scheme.rules', 'csrf');
        $router->post('/{id}/rules/{ruleId}/delete', [SchemeController::class, 'deleteRule'])->middleware('permission:scheme.rules', 'csrf');
        $router->post('/{id}/rules/test',  [SchemeController::class, 'testRules'])->middleware('permission:scheme.rules', 'csrf');

        // Required documents (section 10)
        $router->get('/{id}/documents',  [SchemeController::class, 'documents'])->middleware('permission:scheme.edit');
        $router->post('/{id}/documents', [SchemeController::class, 'saveDocuments'])->middleware('permission:scheme.edit', 'csrf');

        // Multiple benefits (section 11)
        $router->post('/{id}/benefits',  [SchemeController::class, 'saveBenefits'])->middleware('permission:scheme.edit', 'csrf');
    });

    // -----------------------------------------------------------------
    // Beneficiaries & field data collection (sections 17-19, 24)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'beneficiaries'], function ($router) {
        $router->get('/',        [BeneficiaryController::class, 'index'])->middleware('permission:beneficiary.view')->name('beneficiaries.index');
        $router->get('/create',  [BeneficiaryController::class, 'create'])->middleware('permission:beneficiary.create');
        $router->post('/',       [BeneficiaryController::class, 'store'])->middleware('permission:beneficiary.create', 'csrf');
        $router->get('/export',  [BeneficiaryController::class, 'export'])->middleware('permission:beneficiary.export');
        $router->post('/check-duplicate', [BeneficiaryController::class, 'checkDuplicate'])->middleware('permission:beneficiary.create', 'csrf');

        $router->get('/{id}',        [BeneficiaryController::class, 'show'])->middleware('permission:beneficiary.view');
        $router->get('/{id}/edit',   [BeneficiaryController::class, 'edit'])->middleware('permission:beneficiary.edit');
        $router->post('/{id}',       [BeneficiaryController::class, 'update'])->middleware('permission:beneficiary.edit', 'csrf');
        $router->post('/{id}/delete',[BeneficiaryController::class, 'destroy'])->middleware('permission:beneficiary.delete', 'csrf');
        $router->get('/{id}/print',  [BeneficiaryController::class, 'printProfile'])->middleware('permission:beneficiary.view');

        // Socio-economic assessment (section 18) - dynamic questionnaire
        $router->get('/{id}/assessment',  [BeneficiaryController::class, 'assessment'])->middleware('permission:beneficiary.assess');
        $router->post('/{id}/assessment', [BeneficiaryController::class, 'saveAssessment'])->middleware('permission:beneficiary.assess', 'csrf');

        // Household roster
        $router->post('/{id}/members',            [BeneficiaryController::class, 'saveMember'])->middleware('permission:beneficiary.edit', 'csrf');
        $router->post('/{id}/members/{memberId}/delete', [BeneficiaryController::class, 'deleteMember'])->middleware('permission:beneficiary.edit', 'csrf');

        // Documents (section 19)
        $router->get('/{id}/documents',  [DocumentController::class, 'index'])->middleware('permission:beneficiary.document');
        $router->post('/{id}/documents', [DocumentController::class, 'store'])->middleware('permission:beneficiary.document', 'csrf');

        // Scheme matching (section 20)
        $router->get('/{id}/matching',   [MatchingController::class, 'show'])->middleware('permission:beneficiary.match');
        $router->post('/{id}/matching',  [MatchingController::class, 'run'])->middleware('permission:beneficiary.match', 'csrf');
        $router->get('/{id}/matching/{schemeId}/explain', [MatchingController::class, 'explain'])->middleware('permission:beneficiary.match');

        // Field visits
        $router->post('/{id}/visits', [BeneficiaryController::class, 'saveVisit'])->middleware('permission:beneficiary.edit', 'csrf');
    });

    // Document actions that are not nested under a beneficiary
    $router->group(['prefix' => 'documents'], function ($router) {
        $router->get('/{id}/view',     [DocumentController::class, 'view'])->middleware('permission:beneficiary.document');
        $router->get('/{id}/download', [DocumentController::class, 'download'])->middleware('permission:beneficiary.document');
        $router->post('/{id}/verify',  [DocumentController::class, 'verify'])->middleware('permission:beneficiary.verify', 'csrf');
        $router->post('/{id}/delete',  [DocumentController::class, 'destroy'])->middleware('permission:beneficiary.document', 'csrf');
        $router->get('/pending',       [DocumentController::class, 'pending'])->middleware('permission:beneficiary.document');
    });

    // -----------------------------------------------------------------
    // Scheme matching workbench (bulk)
    // -----------------------------------------------------------------
    $router->get('/matching',            [MatchingController::class, 'index'])->middleware('permission:beneficiary.match')->name('matching');
    $router->post('/matching/run-bulk',  [MatchingController::class, 'runBulk'])->middleware('permission:beneficiary.match', 'csrf');
    $router->get('/matching/eligibility-check', [MatchingController::class, 'quickCheck'])->middleware('permission:beneficiary.match');
    $router->post('/matching/eligibility-check',[MatchingController::class, 'runQuickCheck'])->middleware('permission:beneficiary.match', 'csrf');

    // -----------------------------------------------------------------
    // Scheme applications (section 21)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'applications'], function ($router) {
        $router->get('/',       [ApplicationController::class, 'index'])->middleware('permission:application.view')->name('applications.index');
        $router->get('/create', [ApplicationController::class, 'create'])->middleware('permission:application.create');
        $router->post('/',      [ApplicationController::class, 'store'])->middleware('permission:application.create', 'csrf');
        $router->get('/export', [ApplicationController::class, 'export'])->middleware('permission:application.export');
        $router->get('/funnel', [ApplicationController::class, 'funnel'])->middleware('permission:application.view');

        $router->get('/{id}',         [ApplicationController::class, 'show'])->middleware('permission:application.view');
        $router->get('/{id}/edit',    [ApplicationController::class, 'edit'])->middleware('permission:application.edit');
        $router->post('/{id}',        [ApplicationController::class, 'update'])->middleware('permission:application.edit', 'csrf');
        $router->post('/{id}/status', [ApplicationController::class, 'updateStatus'])->middleware('permission:application.edit', 'csrf');
        $router->post('/{id}/benefit',[ApplicationController::class, 'recordBenefit'])->middleware('permission:application.edit', 'csrf');
        $router->post('/{id}/delete', [ApplicationController::class, 'destroy'])->middleware('permission:application.delete', 'csrf');
    });

    // -----------------------------------------------------------------
    // Follow-up management (section 22)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'followups'], function ($router) {
        $router->get('/',        [FollowupController::class, 'index'])->middleware('permission:followup.view')->name('followups.index');
        $router->get('/create',  [FollowupController::class, 'create'])->middleware('permission:followup.create');
        $router->post('/',       [FollowupController::class, 'store'])->middleware('permission:followup.create', 'csrf');
        $router->get('/calendar',[FollowupController::class, 'calendar'])->middleware('permission:followup.view');
        $router->get('/export',  [FollowupController::class, 'export'])->middleware('permission:followup.view');

        $router->get('/{id}',        [FollowupController::class, 'show'])->middleware('permission:followup.view');
        $router->get('/{id}/edit',   [FollowupController::class, 'edit'])->middleware('permission:followup.edit');
        $router->post('/{id}',      [FollowupController::class, 'update'])->middleware('permission:followup.edit', 'csrf');
        $router->post('/{id}/delete',[FollowupController::class, 'destroy'])->middleware('permission:followup.delete', 'csrf');
    });

    // -----------------------------------------------------------------
    // Field data access (sections 23, 27)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'field-data'], function ($router) {
        $router->get('/',            [FieldDataController::class, 'index'])->middleware('permission:fielddata.view')->name('fielddata.index');
        $router->get('/performance', [FieldDataController::class, 'performance'])->middleware('permission:fielddata.performance');
        $router->get('/visits',      [FieldDataController::class, 'visits'])->middleware('permission:fielddata.view');
        $router->get('/export',      [FieldDataController::class, 'export'])->middleware('permission:fielddata.view');
    });

    // -----------------------------------------------------------------
    // Reports (section 26)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'reports'], function ($router) {
        $router->get('/',             [ReportController::class, 'index'])->middleware('permission:report.view')->name('reports.index');
        $router->get('/{report}',     [ReportController::class, 'show'])->middleware('permission:report.view');
        $router->get('/{report}/export', [ReportController::class, 'export'])->middleware('permission:report.export');
        $router->get('/{report}/print',  [ReportController::class, 'printReport'])->middleware('permission:report.view');
    });

    // -----------------------------------------------------------------
    // Masters (section 28)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'masters', 'middleware' => ['permission:master.view']], function ($router) {
        $router->get('/', [MasterController::class, 'index'])->name('masters.index');

        // Geography
        $router->get('/states',                  [MasterController::class, 'states']);
        $router->post('/states',                 [MasterController::class, 'saveState'])->middleware('permission:master.manage', 'csrf');
        $router->post('/states/{id}/toggle',     [MasterController::class, 'toggleState'])->middleware('permission:master.manage', 'csrf');

        $router->get('/districts',               [MasterController::class, 'districts']);
        $router->post('/districts',              [MasterController::class, 'saveDistrict'])->middleware('permission:master.manage', 'csrf');
        $router->post('/districts/{id}/toggle',  [MasterController::class, 'toggleDistrict'])->middleware('permission:master.manage', 'csrf');

        $router->get('/blocks',                  [MasterController::class, 'blocks']);
        $router->post('/blocks',                 [MasterController::class, 'saveBlock'])->middleware('permission:master.manage', 'csrf');
        $router->post('/blocks/{id}/toggle',     [MasterController::class, 'toggleBlock'])->middleware('permission:master.manage', 'csrf');

        $router->get('/gram-panchayats',         [MasterController::class, 'gramPanchayats']);
        $router->post('/gram-panchayats',        [MasterController::class, 'saveGramPanchayat'])->middleware('permission:master.manage', 'csrf');
        $router->post('/gram-panchayats/{id}/toggle', [MasterController::class, 'toggleGramPanchayat'])->middleware('permission:master.manage', 'csrf');

        $router->get('/villages',                [MasterController::class, 'villages']);
        $router->post('/villages',               [MasterController::class, 'saveVillage'])->middleware('permission:master.manage', 'csrf');
        $router->post('/villages/{id}/toggle',   [MasterController::class, 'toggleVillage'])->middleware('permission:master.manage', 'csrf');

        // Domain masters, all driven by one generic controller action
        $router->get('/{master}',                [MasterController::class, 'generic']);
        $router->post('/{master}',               [MasterController::class, 'saveGeneric'])->middleware('permission:master.manage', 'csrf');
        $router->post('/{master}/{id}/toggle',   [MasterController::class, 'toggleGeneric'])->middleware('permission:master.manage', 'csrf');
        $router->post('/{master}/{id}/delete',   [MasterController::class, 'deleteGeneric'])->middleware('permission:master.manage', 'csrf');
    });

    // -----------------------------------------------------------------
    // Administration - users, roles, import, audit, settings
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'users'], function ($router) {
        $router->get('/',        [UserController::class, 'index'])->middleware('permission:user.view')->name('users.index');
        $router->get('/create',  [UserController::class, 'create'])->middleware('permission:user.create');
        $router->post('/',       [UserController::class, 'store'])->middleware('permission:user.create', 'csrf');
        $router->get('/export',  [UserController::class, 'export'])->middleware('permission:user.view');

        $router->get('/{id}',              [UserController::class, 'show'])->middleware('permission:user.view');
        $router->get('/{id}/edit',         [UserController::class, 'edit'])->middleware('permission:user.edit');
        $router->post('/{id}',             [UserController::class, 'update'])->middleware('permission:user.edit', 'csrf');
        $router->post('/{id}/toggle',      [UserController::class, 'toggleActive'])->middleware('permission:user.edit', 'csrf');
        $router->post('/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:user.reset_password', 'csrf');
        $router->post('/{id}/delete',      [UserController::class, 'destroy'])->middleware('permission:user.delete', 'csrf');
    });

    $router->group(['prefix' => 'roles', 'middleware' => ['permission:role.manage']], function ($router) {
        $router->get('/',            [RoleController::class, 'index'])->name('roles.index');
        $router->get('/create',      [RoleController::class, 'create']);
        $router->post('/',           [RoleController::class, 'store'])->middleware('csrf');
        $router->get('/{id}/edit',   [RoleController::class, 'edit']);
        $router->post('/{id}',       [RoleController::class, 'update'])->middleware('csrf');
        $router->post('/{id}/toggle',[RoleController::class, 'toggleActive'])->middleware('csrf');
        $router->post('/{id}/delete',[RoleController::class, 'destroy'])->middleware('csrf');
    });

    // Excel scheme import (section 37)
    $router->group(['prefix' => 'import', 'middleware' => ['permission:scheme.import']], function ($router) {
        $router->get('/schemes',            [SchemeImportController::class, 'index'])->name('import.schemes');
        $router->post('/schemes/upload',    [SchemeImportController::class, 'upload'])->middleware('csrf');
        $router->get('/schemes/{id}/preview',[SchemeImportController::class, 'preview']);
        $router->post('/schemes/{id}/process',[SchemeImportController::class, 'process'])->middleware('csrf');
        $router->post('/schemes/{id}/cancel', [SchemeImportController::class, 'cancel'])->middleware('csrf');
        $router->get('/schemes/{id}/errors', [SchemeImportController::class, 'errors']);
        $router->get('/schemes/template',   [SchemeImportController::class, 'template']);
    });

    // Audit trail (section 29)
    $router->group(['prefix' => 'audit-logs', 'middleware' => ['permission:audit.view']], function ($router) {
        $router->get('/',        [AuditController::class, 'index'])->name('audit.index');
        $router->get('/export',  [AuditController::class, 'export']);
        $router->get('/{id}',    [AuditController::class, 'show']);
    });

    // Settings
    $router->group(['prefix' => 'settings', 'middleware' => ['permission:settings.manage']], function ($router) {
        $router->get('/',  [SettingsController::class, 'index'])->name('settings.index');
        $router->post('/', [SettingsController::class, 'update'])->middleware('csrf');
        $router->get('/assessment-questions', [SettingsController::class, 'assessmentQuestions']);
        $router->post('/assessment-questions',[SettingsController::class, 'saveAssessmentQuestion'])->middleware('csrf');
        $router->post('/assessment-questions/{id}/toggle', [SettingsController::class, 'toggleAssessmentQuestion'])->middleware('csrf');
        $router->get('/eligibility-attributes', [SettingsController::class, 'eligibilityAttributes']);
        $router->post('/eligibility-attributes',[SettingsController::class, 'saveEligibilityAttribute'])->middleware('csrf');
    });

    // -----------------------------------------------------------------
    // Notifications (section 36)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'notifications'], function ($router) {
        $router->get('/',                [NotificationController::class, 'index'])->name('notifications.index');
        $router->get('/dropdown',        [NotificationController::class, 'dropdown']);
        $router->post('/{id}/read',      [NotificationController::class, 'markRead'])->middleware('csrf');
        $router->post('/read-all',       [NotificationController::class, 'markAllRead'])->middleware('csrf');
        $router->post('/generate',       [NotificationController::class, 'generate'])->middleware('permission:settings.manage', 'csrf');
    });

    // -----------------------------------------------------------------
    // Own profile
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'profile'], function ($router) {
        $router->get('/',          [ProfileController::class, 'show'])->name('profile');
        $router->post('/',         [ProfileController::class, 'update'])->middleware('csrf');
        $router->get('/password',  [ProfileController::class, 'showPassword']);
        $router->post('/password', [ProfileController::class, 'updatePassword'])->middleware('csrf');
        $router->get('/activity',  [ProfileController::class, 'activity']);
    });

    // -----------------------------------------------------------------
    // Dependent dropdown endpoints (section 16 - State -> District etc.)
    // -----------------------------------------------------------------
    $router->group(['prefix' => 'geo'], function ($router) {
        $router->get('/states',                     [GeoController::class, 'states']);
        $router->get('/districts/{stateId}',        [GeoController::class, 'districts']);
        $router->get('/blocks/{districtId}',        [GeoController::class, 'blocks']);
        $router->get('/gram-panchayats/{blockId}',  [GeoController::class, 'gramPanchayats']);
        $router->get('/villages/{gpId}',            [GeoController::class, 'villages']);
        $router->get('/villages-by-block/{blockId}',[GeoController::class, 'villagesByBlock']);
    });
});

// The change-password screen must be reachable while a change is pending,
// so it sits outside the 'password' middleware group.
$router->get('/profile/password/change',  [ProfileController::class, 'showPassword'])->middleware('auth');
