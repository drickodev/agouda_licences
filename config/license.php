<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clés de signature Ed25519
    |--------------------------------------------------------------------------
    |
    | Encodées en base64. Générées via: php artisan license:signing-keys:generate
    | La clé privée ne doit JAMAIS quitter le serveur de production.
    |
    */

    'signing_private_key' => env('LICENSE_SIGNING_PRIVATE_KEY'),
    'signing_public_key' => env('LICENSE_SIGNING_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Token d'application
    |--------------------------------------------------------------------------
    |
    | Secret partagé exigé sur chaque requête API via le header X-App-Token.
    |
    */

    'app_token' => env('LICENSE_APP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Fenêtre de fraîcheur des requêtes (anti-rejeu)
    |--------------------------------------------------------------------------
    */

    'nonce_freshness_seconds' => env('LICENSE_NONCE_FRESHNESS_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Alphabet et format des clés de licence
    |--------------------------------------------------------------------------
    |
    | Alphabet sans caractères ambigus (exclut 0/O, 1/I/L).
    |
    */

    'key_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
    'key_groups' => 4,
    'key_group_length' => 5,

    /*
    |--------------------------------------------------------------------------
    | Détection d'anomalies (§7.3.1)
    |--------------------------------------------------------------------------
    |
    | Une même clé vue depuis trop d'IP distinctes sur une fenêtre courte est
    | un signe probable de fuite/partage. Heuristique volontairement simple :
    | pas de géolocalisation IP (nécessiterait une base tierce), seulement le
    | nombre d'IP distinctes observées.
    |
    */

    'anomaly' => [
        'distinct_ip_threshold' => env('LICENSE_ANOMALY_IP_THRESHOLD', 5),
        'window_hours' => env('LICENSE_ANOMALY_WINDOW_HOURS', 24),
        'auto_revoke' => env('LICENSE_ANOMALY_AUTO_REVOKE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Détection d'abus du X-App-Token partagé (§audit sécurité, point 2)
    |--------------------------------------------------------------------------
    |
    | Contrairement à `anomaly` ci-dessus (raisonne par clé de licence), ceci
    | surveille le nombre d'empreintes machine distinctes vues tous clients
    | confondus : un signe possible de fuite du token unique.
    |
    */

    'token_abuse' => [
        'distinct_fingerprint_threshold' => env('LICENSE_TOKEN_ABUSE_FINGERPRINT_THRESHOLD', 50),
        'window_minutes' => env('LICENSE_TOKEN_ABUSE_WINDOW_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Durcissement du panel admin (§7.3.4)
    |--------------------------------------------------------------------------
    |
    | Liste blanche d'IP/CIDR autorisées à accéder à /admin. Vide = pas de
    | restriction (déconseillé en production).
    |
    */

    'admin_allowed_ips' => array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_ALLOWED_IPS', ''))
    )),

    /*
    |--------------------------------------------------------------------------
    | Documentation Swagger (§audit sécurité, point 1)
    |--------------------------------------------------------------------------
    |
    | /api/documentation et /docs/api-docs.json exposent la totalité des
    | routes admin : protégés par Basic Auth (identifiants vides = doc
    | désactivée) en plus de l'IP allowlist ci-dessus.
    |
    */

    'docs' => [
        'username' => env('SWAGGER_DOCS_USERNAME'),
        'password' => env('SWAGGER_DOCS_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Déblocage de compte local ("mot de passe oublié")
    |--------------------------------------------------------------------------
    |
    | Sans rapport avec les clés de licence : un logiciel client peut gérer
    | ses propres comptes utilisateurs en local (mot de passe stocké dans sa
    | base locale). En l'absence de portail client, l'éditeur génère un code
    | à usage unique depuis le panel admin quand un client le contacte ; le
    | logiciel le redeem via l'API et reçoit un payload signé l'autorisant
    | à laisser l'utilisateur redéfinir son mot de passe local.
    |
    */

    'account_recovery' => [
        'code_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'code_length' => 8,
        'ttl_minutes' => env('LICENSE_ACCOUNT_RECOVERY_TTL_MINUTES', 30),
    ],

];
