<?php
/**
 * JAN KAVACH - Database Configuration
 *
 * The application talks to MySQL exclusively through PDO with emulated
 * prepares turned OFF, so every query is a genuine server-side prepared
 * statement.  That is the project's primary defence against SQL injection.
 */

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'port'      => 3306,
            'database'  => 'jan_kavach',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => false,
            ],
        ],
    ],
];
