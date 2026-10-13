<?php
/**
 * JAN KAVACH - API Routes
 *
 * These endpoints exist so the future mobile application can reuse the same
 * business logic. They share the session guard for now; swapping in a token
 * guard later means changing the middleware here and adding a TokenMiddleware
 * - no controller or model change is required.
 *
 * Every response is JSON via Response::success() / Response::error().
 */

use JanKavach\Controllers\Api\ApiApplicationController;
use JanKavach\Controllers\Api\ApiBeneficiaryController;
use JanKavach\Controllers\Api\ApiDashboardController;
use JanKavach\Controllers\Api\ApiGeoController;
use JanKavach\Controllers\Api\ApiMasterController;
use JanKavach\Controllers\Api\ApiMatchingController;
use JanKavach\Controllers\Api\ApiSchemeController;

/** @var JanKavach\Core\Router $router */

$router->group(['prefix' => 'api/v1'], function ($router) {

    // Health check - no auth, so a mobile client can detect the server.
    $router->get('/ping', function () {
        \JanKavach\Core\Response::success([
            'app'     => \JanKavach\Core\Config::get('app.name'),
            'version' => \JanKavach\Core\Config::get('app.version'),
            'time'    => date('c'),
        ], 'Jan Kavach API is reachable');
    });

    $router->group(['middleware' => ['auth']], function ($router) {

        // Signed-in user context
        $router->get('/me', function () {
            $user = \JanKavach\Core\Auth::user();
            \JanKavach\Core\Response::success([
                'id'          => (int)$user['id'],
                'code'        => $user['user_code'],
                'name'        => $user['full_name'],
                'email'       => $user['email'],
                'role'        => $user['role_code'],
                'role_name'   => $user['role_name'],
                'data_scope'  => $user['data_scope'],
                'state'       => ['id' => $user['state_id'] ? (int)$user['state_id'] : null, 'name' => $user['state_name']],
                'district'    => ['id' => $user['district_id'] ? (int)$user['district_id'] : null, 'name' => $user['district_name']],
                'permissions' => \JanKavach\Core\Auth::permissions(),
            ]);
        });

        // ---------------------------------------------------------------
        // Masters & geography - what a mobile app caches on first sync
        // ---------------------------------------------------------------
        $router->get('/masters',                      [ApiMasterController::class, 'all']);
        $router->get('/masters/{key}',                [ApiMasterController::class, 'lookup']);
        $router->get('/geo/states',                   [ApiGeoController::class, 'states']);
        $router->get('/geo/districts/{stateId}',      [ApiGeoController::class, 'districts']);
        $router->get('/geo/blocks/{districtId}',      [ApiGeoController::class, 'blocks']);
        $router->get('/geo/gram-panchayats/{blockId}',[ApiGeoController::class, 'gramPanchayats']);
        $router->get('/geo/villages/{gpId}',          [ApiGeoController::class, 'villages']);

        // ---------------------------------------------------------------
        // Schemes
        // ---------------------------------------------------------------
        $router->get('/schemes',            [ApiSchemeController::class, 'index'])->middleware('permission:scheme.view');
        $router->get('/schemes/search',     [ApiSchemeController::class, 'search'])->middleware('permission:scheme.view');
        $router->get('/schemes/{id}',       [ApiSchemeController::class, 'show'])->middleware('permission:scheme.view');
        $router->get('/schemes/{id}/rules', [ApiSchemeController::class, 'rules'])->middleware('permission:scheme.view');
        $router->get('/schemes/{id}/documents', [ApiSchemeController::class, 'documents'])->middleware('permission:scheme.view');

        // ---------------------------------------------------------------
        // Beneficiaries - the main field-app surface
        // ---------------------------------------------------------------
        $router->get('/beneficiaries',        [ApiBeneficiaryController::class, 'index'])->middleware('permission:beneficiary.view');
        $router->post('/beneficiaries',       [ApiBeneficiaryController::class, 'store'])->middleware('permission:beneficiary.create', 'csrf');
        $router->get('/beneficiaries/{id}',   [ApiBeneficiaryController::class, 'show'])->middleware('permission:beneficiary.view');
        $router->post('/beneficiaries/{id}',  [ApiBeneficiaryController::class, 'update'])->middleware('permission:beneficiary.edit', 'csrf');
        $router->get('/beneficiaries/{id}/assessment',  [ApiBeneficiaryController::class, 'assessment'])->middleware('permission:beneficiary.assess');
        $router->post('/beneficiaries/{id}/assessment', [ApiBeneficiaryController::class, 'saveAssessment'])->middleware('permission:beneficiary.assess', 'csrf');
        $router->get('/beneficiaries/{id}/documents',   [ApiBeneficiaryController::class, 'documents'])->middleware('permission:beneficiary.document');

        // Assessment questionnaire definition, so the app can render it
        // without a release whenever a question is added.
        $router->get('/assessment-questions', [ApiBeneficiaryController::class, 'questions']);

        // ---------------------------------------------------------------
        // Matching
        // ---------------------------------------------------------------
        $router->get('/beneficiaries/{id}/matches',  [ApiMatchingController::class, 'matches'])->middleware('permission:beneficiary.match');
        $router->post('/beneficiaries/{id}/matches', [ApiMatchingController::class, 'run'])->middleware('permission:beneficiary.match', 'csrf');
        $router->post('/matching/simulate',          [ApiMatchingController::class, 'simulate'])->middleware('permission:beneficiary.match', 'csrf');

        // ---------------------------------------------------------------
        // Applications & follow-ups
        // ---------------------------------------------------------------
        $router->get('/applications',             [ApiApplicationController::class, 'index'])->middleware('permission:application.view');
        $router->post('/applications',            [ApiApplicationController::class, 'store'])->middleware('permission:application.create', 'csrf');
        $router->get('/applications/{id}',        [ApiApplicationController::class, 'show'])->middleware('permission:application.view');
        $router->post('/applications/{id}/status',[ApiApplicationController::class, 'updateStatus'])->middleware('permission:application.edit', 'csrf');
        $router->post('/applications/{id}/followups', [ApiApplicationController::class, 'addFollowup'])->middleware('permission:followup.create', 'csrf');
        $router->get('/followups/due',            [ApiApplicationController::class, 'followupsDue'])->middleware('permission:followup.view');

        // ---------------------------------------------------------------
        // Dashboard aggregates
        // ---------------------------------------------------------------
        $router->get('/dashboard/kpis',   [ApiDashboardController::class, 'kpis']);
        $router->get('/dashboard/charts', [ApiDashboardController::class, 'charts']);
    });
});
