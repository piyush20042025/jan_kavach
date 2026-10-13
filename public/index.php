<?php
/**
 * JAN KAVACH - Front Controller
 *
 * Every HTTP request enters here. Nothing else in the application should be
 * directly reachable from the web root; app/, config/, storage/, routes/ and
 * database/ all sit above it and are additionally blocked by .htaccess.
 */

declare(strict_types=1);

// Fail loudly if the PHP build is too old for the language features used.
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Jan Kavach requires PHP 8.0 or newer. Detected: ' . PHP_VERSION);
}

// The bootstrapper owns configuration loading; index.php only needs enough
// to locate it.  config/config.php defines the BASE_PATH family of constants.
$root = dirname(__DIR__);

require_once $root . '/app/core/App.php';

$app = new JanKavach\Core\App();
$app->run();
