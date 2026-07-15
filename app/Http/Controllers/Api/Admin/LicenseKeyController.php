<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexLicenseKeyRequest;
use App\Http\Requests\Api\Admin\RevealLicenseKeyRequest;
use App\Http\Requests\Api\Admin\RevokeLicenseKeyRequest;
use App\Http\Requests\Api\Admin\StoreLicenseKeyRequest;
use App\Http\Requests\Api\Admin\UpdateLicenseKeyRequest;
use App\Http\Resources\Admin\LicenseKeyResource;
use App\Models\LicenseKey;
use App\Models\LicenseLog;
use App\Services\LicenseKeyGenerator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use PragmaRX\Google2FAQRCode\Google2FA;

#[OA\Tag(name: 'Admin - Clés de licence')]
class LicenseKeyController extends Controller
{
    public function __construct(
        private readonly LicenseKeyGenerator $generator,
        private readonly Google2FA $google2fa,
    ) {
    }

    #[OA\Get(
        path: '/api/admin/license-keys',
        summary: 'Liste les clés de licence (masquées)',
        security: [['sanctum' => []]],
        tags: ['Admin - Clés de licence'],
        parameters: [
            new OA\Parameter(name: 'plaintext_key', in: 'query', description: 'Recherche par clé complète en clair (hashée côté serveur)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'product_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'license_type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['perpetual', 'subscription'])),
            new OA\Parameter(name: 'revoked', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
            properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LicenseKey'))],
        ))],
    )]
    public function index(IndexLicenseKeyRequest $request)
    {
        $filters = $request->validated();

        $query = LicenseKey::query()
            ->with(['product', 'customer'])
            ->withCount('activeActivations');

        if (! empty($filters['plaintext_key'])) {
            $query->wherePlaintextKey($filters['plaintext_key']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['license_type'])) {
            $query->where('license_type', $filters['license_type']);
        }

        if (array_key_exists('revoked', $filters) && $filters['revoked'] !== null) {
            $query->where('revoked', (bool) $filters['revoked']);
        }

        return LicenseKeyResource::collection($query->latest()->paginate());
    }

    #[OA\Post(
        path: '/api/admin/license-keys',
        summary: 'Génère une clé de licence',
        description: "La clé en clair (`plaintext_key`) n'est présente qu'une seule fois dans cette réponse ; "
            ."utiliser GET /license-keys/{id}/reveal pour la reconsulter ensuite.",
        security: [['sanctum' => []]],
        tags: ['Admin - Clés de licence'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['product_id', 'license_type', 'max_activations'],
            properties: [
                new OA\Property(property: 'key', type: 'string', nullable: true, description: 'Laisser vide pour générer automatiquement'),
                new OA\Property(property: 'product_id', type: 'integer'),
                new OA\Property(property: 'customer_id', type: 'integer', nullable: true),
                new OA\Property(property: 'license_type', type: 'string', enum: ['perpetual', 'subscription']),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true, description: 'Requis si subscription'),
                new OA\Property(property: 'max_activations', type: 'integer', example: 1),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Créée', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'plaintext_key', type: 'string'),
                new OA\Property(property: 'license_key', ref: '#/components/schemas/LicenseKey'),
            ])),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreLicenseKeyRequest $request)
    {
        $data = $request->validated();

        $plaintextKey = $data['key'] ?? $this->generator->generateUniqueKey();

        if (! empty($data['key'])) {
            $exists = LicenseKey::query()->wherePlaintextKey($plaintextKey)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'key' => ['Cette clé existe déjà.'],
                ]);
            }
        }

        $licenseKey = LicenseKey::query()->create([
            'key_hash' => LicenseKeyGenerator::hash($plaintextKey),
            'key_last4' => substr($plaintextKey, -4),
            'key_encrypted' => $plaintextKey,
            'product_id' => $data['product_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'license_type' => $data['license_type'],
            'expires_at' => $data['expires_at'] ?? null,
            'max_activations' => $data['max_activations'],
            'notes' => $data['notes'] ?? null,
            'revoked' => false,
        ]);

        $licenseKey->load(['product', 'customer'])->loadCount('activeActivations');

        return response()->json([
            'plaintext_key' => $plaintextKey,
            'license_key' => LicenseKeyResource::make($licenseKey),
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/license-keys/{license_key}',
        summary: "Détail d'une clé de licence",
        security: [['sanctum' => []]],
        tags: ['Admin - Clés de licence'],
        parameters: [new OA\Parameter(name: 'license_key', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/LicenseKey'),
        ]))],
    )]
    public function show(LicenseKey $licenseKey)
    {
        $licenseKey->load(['product', 'customer'])->loadCount('activeActivations');

        return LicenseKeyResource::make($licenseKey);
    }

    #[OA\Put(
        path: '/api/admin/license-keys/{license_key}',
        summary: 'Modifie une clé de licence (y compris révoquer/dé-révoquer manuellement)',
        security: [['sanctum' => []]],
        tags: ['Admin - Clés de licence'],
        parameters: [new OA\Parameter(name: 'license_key', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['product_id', 'license_type', 'max_activations', 'revoked'],
            properties: [
                new OA\Property(property: 'product_id', type: 'integer'),
                new OA\Property(property: 'customer_id', type: 'integer', nullable: true),
                new OA\Property(property: 'license_type', type: 'string', enum: ['perpetual', 'subscription']),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'max_activations', type: 'integer'),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
                new OA\Property(property: 'revoked', type: 'boolean'),
                new OA\Property(property: 'revoked_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'revoked_reason', type: 'string', nullable: true),
            ],
        )),
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/LicenseKey'),
        ]))],
    )]
    public function update(UpdateLicenseKeyRequest $request, LicenseKey $licenseKey)
    {
        $data = $request->validated();

        $licenseKey->update([
            'product_id' => $data['product_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'license_type' => $data['license_type'],
            'expires_at' => $data['expires_at'] ?? null,
            'max_activations' => $data['max_activations'],
            'notes' => $data['notes'] ?? null,
            'revoked' => $data['revoked'],
            'revoked_at' => $data['revoked'] ? ($data['revoked_at'] ?? now()) : null,
            'revoked_reason' => $data['revoked'] ? ($data['revoked_reason'] ?? null) : null,
        ]);

        $licenseKey->load(['product', 'customer'])->loadCount('activeActivations');

        return LicenseKeyResource::make($licenseKey);
    }

    #[OA\Post(
        path: '/api/admin/license-keys/{license_key}/revoke',
        summary: 'Révoque une clé (motif obligatoire)',
        security: [['sanctum' => []]],
        tags: ['Admin - Clés de licence'],
        parameters: [new OA\Parameter(name: 'license_key', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [new OA\Property(property: 'reason', type: 'string', example: 'Impayé')],
        )),
        responses: [new OA\Response(response: 200, description: 'Révoquée', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/LicenseKey'),
        ]))],
    )]
    public function revoke(RevokeLicenseKeyRequest $request, LicenseKey $licenseKey)
    {
        $licenseKey->update([
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_reason' => $request->validated('reason'),
        ]);

        $licenseKey->load(['product', 'customer'])->loadCount('activeActivations');

        return LicenseKeyResource::make($licenseKey);
    }

    #[OA\Delete(
        path: '/api/admin/license-keys/{license_key}',
        summary: 'Supprime définitivement une clé de licence',
        security: [['sanctum' => []]],
        tags: ['Admin - Clés de licence'],
        parameters: [new OA\Parameter(name: 'license_key', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimée')],
    )]
    public function destroy(LicenseKey $licenseKey)
    {
        $licenseKey->delete();

        return response()->json(status: 204);
    }

    /**
     * Redonne accès à la clé en clair (déchiffrée depuis key_encrypted) en
     * cas de perte côté éditeur. Absente pour les clés créées avant
     * l'introduction de ce champ.
     *
     * Action sensible (§audit sécurité, point 3) : exige une re-confirmation
     * 2FA immédiate (code TOTP dans le body), même avec un token Sanctum
     * valide et une session active, throttlée séparément et journalisée
     * dans license_logs (admin, IP, horodatage).
     */
    #[OA\Post(
        path: '/api/admin/license-keys/{license_key}/reveal',
        summary: 'Reconsulte la clé en clair (nécessite un code 2FA)',
        description: 'Re-confirmation 2FA immédiate requise même si le compte a une session active.',
        security: [['sanctum' => []]],
        tags: ['Admin - Clés de licence'],
        parameters: [new OA\Parameter(name: 'license_key', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code'],
            properties: [new OA\Property(property: 'code', type: 'string', example: '123456')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'plaintext_key', type: 'string'),
            ])),
            new OA\Response(response: 404, description: "Valeur en clair non disponible (clé créée avant l'ajout de ce stockage)"),
            new OA\Response(response: 422, description: 'Code 2FA invalide', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ],
    )]
    public function reveal(RevealLicenseKeyRequest $request, LicenseKey $licenseKey)
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled() || ! $this->google2fa->verifyKey($user->two_factor_secret, $request->validated('code'))) {
            LicenseLog::query()->create([
                'license_key_id' => $licenseKey->id,
                'event' => 'license_key_reveal',
                'ip' => $request->ip(),
                'success' => false,
                'reason' => 'invalid_2fa_code',
                'meta' => ['admin_id' => $user->id, 'admin_email' => $user->email],
            ]);

            throw ValidationException::withMessages(['code' => ['Code invalide.']]);
        }

        LicenseLog::query()->create([
            'license_key_id' => $licenseKey->id,
            'event' => 'license_key_reveal',
            'ip' => $request->ip(),
            'success' => $licenseKey->key_encrypted !== null,
            'reason' => $licenseKey->key_encrypted === null ? 'plaintext_unavailable' : null,
            'meta' => ['admin_id' => $user->id, 'admin_email' => $user->email],
        ]);

        if ($licenseKey->key_encrypted === null) {
            return response()->json([
                'message' => 'La valeur en clair de cette clé n\'est plus disponible (créée avant cette fonctionnalité).',
            ], 404);
        }

        return response()->json([
            'plaintext_key' => $licenseKey->key_encrypted,
        ]);
    }
}
