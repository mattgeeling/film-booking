<?php
// Copy this file to config.php and fill in real values. config.php is gitignored.

return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'film_plan',
        'user' => 'film_plan',
        'pass' => 'change-me',
    ],

    // When true, Calendar API calls are logged instead of actually made.
    // Keep this on until the Google service account / domain-wide delegation is set up.
    'calendar_dry_run' => true,

    'google' => [
        'service_account_key_path' => __DIR__ . '/service-account.json',
        'oauth_web_client_id' => '',
        'workspace_domain' => '',
    ],

    'mail' => [
        'from_email' => 'production@fuzzyduck.co.uk',
        'from_name' => 'Film Plan',
    ],

    'timezone' => 'Europe/London',
];
