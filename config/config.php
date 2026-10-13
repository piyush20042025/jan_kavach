<?php
/**
 * JAN KAVACH - Application Configuration
 *
 * Every environment-specific value lives here. Nothing else in the
 * application should hard-code a path, URL, limit or credential.
 */

// ---------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------
define('BASE_PATH',       dirname(__DIR__));
define('APP_PATH',        BASE_PATH . '/app');
define('CONFIG_PATH',     BASE_PATH . '/config');
define('VIEW_PATH',       APP_PATH  . '/views');
define('ROUTES_PATH',     BASE_PATH . '/routes');
define('STORAGE_PATH',    BASE_PATH . '/storage');
define('DOCUMENT_PATH',   STORAGE_PATH . '/documents');
define('LOG_PATH',        STORAGE_PATH . '/logs');
define('IMPORT_PATH',     STORAGE_PATH . '/imports');
define('EXPORT_PATH',     STORAGE_PATH . '/exports');
define('PUBLIC_PATH',     BASE_PATH . '/public');

return [

    // -----------------------------------------------------------------
    // Application identity
    // -----------------------------------------------------------------
    'app' => [
        'name'        => 'Jan Kavach',
        'tagline'     => 'Connecting Citizens with Social Security Schemes',
        'full_name'   => 'Jan Kavach - Social Security Scheme Information, Beneficiary Assessment & Field Data Management Platform',
        'short_name'  => 'JK',
        'version'     => '1.0.0',
        'owner'       => 'Social Protection Programme',
        'env'         => 'development',          // development | production
        'debug'       => true,                   // set FALSE in production
        'timezone'    => 'Asia/Kolkata',
        'locale'      => 'en_IN',
        'currency'    => 'INR',
        'currency_symbol' => "\u{20B9}",
        'date_format'     => 'd-m-Y',
        'datetime_format' => 'd-m-Y h:i A',
    ],

    // -----------------------------------------------------------------
    // URLs.  base_url is auto-detected so the app works from any folder
    // (http://localhost/FXB/jan-kavach/public) or from a vhost root.
    // Override manually in production if behind a proxy.
    // -----------------------------------------------------------------
    'url' => [
        'base'   => null,        // null = auto-detect
        'assets' => 'assets',
    ],

    // -----------------------------------------------------------------
    // Session & security
    // -----------------------------------------------------------------
    'session' => [
        'name'              => 'JANKAVACH_SESSID',
        'lifetime'          => 3600,   // seconds of inactivity before timeout
        'regenerate_every'  => 300,    // rotate session id every 5 minutes
        'cookie_httponly'   => true,
        'cookie_secure'     => false,  // TRUE once served over HTTPS
        'cookie_samesite'   => 'Lax',
        'remember_lifetime' => 1209600, // 14 days
    ],

    'security' => [
        'csrf_token_name'      => '_token',
        'csrf_lifetime'        => 7200,
        'password_min_length'  => 8,
        'password_algo'        => PASSWORD_BCRYPT,
        'password_cost'        => 11,
        'max_login_attempts'   => 5,
        'lockout_minutes'      => 15,
        'login_window_minutes' => 15,
        'reset_token_lifetime' => 3600,
        // Pepper used when hashing Aadhaar for duplicate detection.
        // CHANGE THIS before going live and never rotate it afterwards.
        'aadhaar_pepper'       => 'jan-kavach-change-this-pepper-in-production',
    ],

    // -----------------------------------------------------------------
    // File uploads
    // -----------------------------------------------------------------
    'upload' => [
        'max_size'          => 5 * 1024 * 1024,           // 5 MB per document
        'max_photo_size'    => 2 * 1024 * 1024,
        'allowed_ext'       => ['pdf', 'jpg', 'jpeg', 'png'],
        'allowed_mime'      => [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ],
        'import_ext'        => ['xlsx', 'xls', 'csv'],
        'import_mime'       => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/octet-stream',
            'text/csv',
            'text/plain',
            'application/zip',
        ],
        'import_max_size'   => 10 * 1024 * 1024,
    ],

    // -----------------------------------------------------------------
    // Pagination
    // -----------------------------------------------------------------
    'pagination' => [
        'per_page'         => 25,
        'per_page_options' => [10, 25, 50, 100],
        'max_per_page'     => 500,
        'export_max_rows'  => 20000,
    ],

    // -----------------------------------------------------------------
    // Eligibility matching engine thresholds
    // -----------------------------------------------------------------
    'matching' => [
        // A scheme with no failed mandatory rule is graded on match %.
        'eligible_threshold'   => 90,   // >= 90% and nothing unknown  -> Eligible
        'potential_threshold'  => 60,   // >= 60%                      -> Potentially Eligible
        // Below potential_threshold but nothing failed -> Needs Verification
        'store_not_eligible'   => true, // keep negative matches so "why not" can be shown
        'min_percent_to_store' => 0,
    ],

    // -----------------------------------------------------------------
    // Notifications - channel scaffolding for future integrations
    // -----------------------------------------------------------------
    'notifications' => [
        'followup_due_days'    => 3,    // look-ahead window for "upcoming"
        'channels' => [
            'inapp'    => true,
            'email'    => false,
            'sms'      => false,
            'whatsapp' => false,
        ],
    ],

    // -----------------------------------------------------------------
    // Mail (used by password reset; disabled by default in dev)
    // -----------------------------------------------------------------
    'mail' => [
        'enabled'    => false,
        'from_email' => 'no-reply@jankavach.local',
        'from_name'  => 'Jan Kavach',
    ],

    // -----------------------------------------------------------------
    // Logging
    // -----------------------------------------------------------------
    'log' => [
        'enabled' => true,
        'level'   => 'debug',   // debug | info | warning | error
        'file'    => LOG_PATH . '/app.log',
    ],
];
