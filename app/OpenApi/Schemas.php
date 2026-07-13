<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Schémas de données réutilisés par les annotations OpenAPI des
 * contrôleurs (aucune route associée).
 */
#[OA\Schema(
    schema: 'Product',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'AGOUDA SYSCOHADA'),
        new OA\Property(property: 'slug', type: 'string', example: 'agouda_syscohada'),
        new OA\Property(property: 'edition', type: 'string', nullable: true, example: 'pro'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Customer',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Jean Dupont'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'jean@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'company', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'LicenseKey',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'key_last4', type: 'string', example: 'YJTE'),
        new OA\Property(property: 'masked_key', type: 'string', example: '••••-••••-••••-YJTE'),
        new OA\Property(property: 'product', ref: '#/components/schemas/Product'),
        new OA\Property(property: 'customer', ref: '#/components/schemas/Customer', nullable: true),
        new OA\Property(property: 'license_type', type: 'string', enum: ['perpetual', 'subscription']),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'max_activations', type: 'integer', example: 2),
        new OA\Property(property: 'active_activations_count', type: 'integer', example: 0),
        new OA\Property(property: 'revoked', type: 'boolean'),
        new OA\Property(property: 'revoked_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'revoked_reason', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Activation',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'license_key_id', type: 'integer'),
        new OA\Property(property: 'license_key', ref: '#/components/schemas/LicenseKey'),
        new OA\Property(property: 'machine_fingerprint', type: 'string', example: 'sha256:abc123...'),
        new OA\Property(property: 'machine_name', type: 'string', nullable: true, example: 'PC-Comptoir'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'released']),
        new OA\Property(property: 'last_ip', type: 'string', nullable: true),
        new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'released_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'LicenseLog',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'license_key_id', type: 'integer', nullable: true),
        new OA\Property(property: 'license_key', ref: '#/components/schemas/LicenseKey'),
        new OA\Property(property: 'event', type: 'string', example: 'activate'),
        new OA\Property(property: 'machine_fingerprint', type: 'string', nullable: true),
        new OA\Property(property: 'ip', type: 'string', nullable: true),
        new OA\Property(property: 'success', type: 'boolean'),
        new OA\Property(property: 'reason', type: 'string', nullable: true),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'AccountRecoveryCode',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'activation', ref: '#/components/schemas/Activation'),
        new OA\Property(property: 'created_by', type: 'object', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'used_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'is_used', type: 'boolean'),
        new OA\Property(property: 'used_from_ip', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'SignedPayload',
    description: 'Réponse signée Ed25519 commune à activate/validate/deactivate/account-recovery.',
    properties: [
        new OA\Property(property: 'payload', type: 'string', description: 'JSON brut du payload, à vérifier tel quel avant parsing'),
        new OA\Property(property: 'signature', type: 'string', description: 'Signature Ed25519 détachée, base64'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'errors', type: 'object'),
    ],
)]
class Schemas
{
    //
}
