<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Point d'ancrage pour les métadonnées globales de la spec OpenAPI (aucune
 * route associée) : titre, serveurs, schémas de sécurité partagés par tous
 * les contrôleurs annotés.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'API Licences',
    description: "API de gestion de licences logicielles.\n\n"
        ."Deux faces : `/api/v1/*` (licences, appelée par les logiciels clients, protégée par le header `X-App-Token`) "
        ."et `/api/admin/*` (administration, appelée par l'app Flutter, protégée par token Bearer Sanctum + 2FA optionnel).",
)]
#[OA\Server(url: '/', description: 'Serveur courant')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token Sanctum',
    description: "Token obtenu via POST /api/admin/login (ou /api/admin/2fa/challenge si le 2FA est activé). "
        ."À envoyer en en-tête `Authorization: Bearer <token>`.",
)]
#[OA\SecurityScheme(
    securityScheme: 'appToken',
    type: 'apiKey',
    in: 'header',
    name: 'X-App-Token',
    description: "Token d'application partagé, exigé sur tous les endpoints /api/v1/*.",
)]
class OpenApiSpec
{
    //
}
