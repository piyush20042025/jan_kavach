<?php

/**
 * JAN KAVACH - Mobile User API Routes
 *
 * Base:
 * /api/mobile/user
 */

use JanKavach\Controllers\mobile_api\AuthController;
use JanKavach\Controllers\mobile_api\DashboardController;
use JanKavach\Controllers\mobile_api\BeneficiaryController;
use JanKavach\Controllers\mobile_api\BeneficiaryDetailController;
use JanKavach\Controllers\mobile_api\BeneficiaryRegistrationController;
use JanKavach\Controllers\mobile_api\EligibleSchemesController;
use JanKavach\Controllers\mobile_api\MobileSchemeController;
use JanKavach\Controllers\mobile_api\GeographyController;


/** @var JanKavach\Core\Router $router */

/*
|--------------------------------------------------------------------------
| Mobile User API
|--------------------------------------------------------------------------
*/

$router->group(
    [
        'prefix' => 'api/mobile/user'
    ],
    function ($router) {

        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */

        $router->post(
            '/auth/login',
            [AuthController::class, 'login']
        );

        $router->post(
            '/auth/refresh',
            [AuthController::class, 'refresh']
        );

        /*
        |--------------------------------------------------------------------------
        | Protected APIs
        |--------------------------------------------------------------------------
        */
        $router->group(
            [
                'middleware' => ['mobile_auth']
            ],
            function ($router) {

                $router->get(
                    '/dashboard',
                    [DashboardController::class, 'index']
                );

                $router->get(
                    '/beneficiaries',
                    [BeneficiaryController::class, 'index']
                );
                $router->get(
                    '/beneficiaries/{id}',
                    [BeneficiaryDetailController::class, 'show']
                );
                $router->post(
                    '/beneficiaries',
                    [BeneficiaryRegistrationController::class, 'store']
                );
                $router->get(
                    '/beneficiaries/{id}/eligible-schemes',
                    [EligibleSchemesController::class, 'show']
                );
                $router->get(
                    '/beneficiaries/{id}/eligible-schemes/next',
                    [EligibleSchemesController::class, 'show']
                );
                $router->post(
                    '/beneficiaries/{id}/eligible-schemes',
                    [EligibleSchemesController::class, 'match']
                );
                $router->post(
                    '/beneficiaries/{id}/eligible-schemes/next',
                    [EligibleSchemesController::class, 'match']
                );
                $router->get(
                    '/schemes',
                    [MobileSchemeController::class, 'index']
                );

                $router->get(
                    '/schemes/{id}',
                    [MobileSchemeController::class, 'show']
                );


                $router->get('/geography/states', [GeographyController::class, 'states']);
                $router->get('/geography/districts/{stateId}', [GeographyController::class, 'districts']);
                $router->get('/geography/blocks/{districtId}', [GeographyController::class, 'blocks']);
                $router->get('/geography/gram-panchayats/{blockId}', [GeographyController::class, 'gramPanchayats']);
                $router->get('/geography/villages/{gpId}', [GeographyController::class, 'villages']);
                $router->get('/geography/villages-by-block/{blockId}', [GeographyController::class, 'villagesByBlock']);

                $router->post(
                    '/auth/logout',
                    [AuthController::class, 'logout']
                );
            }
        );
    }
);