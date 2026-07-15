<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | API interne (§audit sécurité, point 5) : consommée par des clients
    | natifs (logiciel Flutter Windows, app admin Flutter Windows), aucun
    | appelé n'est un navigateur. Aucune origine n'est autorisée par défaut
    | ("*" renvoyé précédemment via le fallback du framework, faute de ce
    | fichier) ; ADMIN_CORS_ALLOWED_ORIGINS permet d'en whitelister si un
    | panel web venait à exister.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
