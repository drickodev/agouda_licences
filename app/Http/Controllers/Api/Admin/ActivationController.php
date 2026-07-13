<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\GenerateAccountRecoveryCodeRequest;
use App\Http\Requests\Api\Admin\IndexActivationRequest;
use App\Http\Requests\Api\Admin\StoreActivationRequest;
use App\Http\Requests\Api\Admin\UpdateActivationRequest;
use App\Http\Resources\Admin\ActivationResource;
use App\Models\AccountRecoveryCode;
use App\Models\Activation;
use App\Services\AccountRecoveryCodeGenerator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Activations')]
class ActivationController extends Controller
{
    public function __construct(private readonly AccountRecoveryCodeGenerator $codeGenerator)
    {
    }

    #[OA\Get(
        path: '/api/admin/activations',
        summary: 'Liste les activations',
        security: [['sanctum' => []]],
        tags: ['Admin - Activations'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'released'])),
            new OA\Parameter(name: 'license_key_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
            properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Activation'))],
        ))],
    )]
    public function index(IndexActivationRequest $request)
    {
        $filters = $request->validated();

        $query = Activation::query()->with('licenseKey.product', 'licenseKey.customer');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['license_key_id'])) {
            $query->where('license_key_id', $filters['license_key_id']);
        }

        return ActivationResource::collection($query->latest()->paginate());
    }

    #[OA\Post(
        path: '/api/admin/activations',
        summary: 'Crée une activation manuellement',
        security: [['sanctum' => []]],
        tags: ['Admin - Activations'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['license_key_id', 'machine_fingerprint', 'status'],
            properties: [
                new OA\Property(property: 'license_key_id', type: 'integer'),
                new OA\Property(property: 'machine_fingerprint', type: 'string'),
                new OA\Property(property: 'machine_name', type: 'string', nullable: true),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'released']),
                new OA\Property(property: 'last_ip', type: 'string', nullable: true),
                new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'released_at', type: 'string', format: 'date-time', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Créée', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Activation'),
            ])),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreActivationRequest $request)
    {
        $activation = Activation::query()->create($request->validated());
        $activation->load('licenseKey.product', 'licenseKey.customer');

        return ActivationResource::make($activation)->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/admin/activations/{activation}',
        summary: "Détail d'une activation",
        security: [['sanctum' => []]],
        tags: ['Admin - Activations'],
        parameters: [new OA\Parameter(name: 'activation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/Activation'),
        ]))],
    )]
    public function show(Activation $activation)
    {
        $activation->load('licenseKey.product', 'licenseKey.customer');

        return ActivationResource::make($activation);
    }

    #[OA\Put(
        path: '/api/admin/activations/{activation}',
        summary: 'Modifie une activation',
        security: [['sanctum' => []]],
        tags: ['Admin - Activations'],
        parameters: [new OA\Parameter(name: 'activation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['license_key_id', 'machine_fingerprint', 'status'],
            properties: [
                new OA\Property(property: 'license_key_id', type: 'integer'),
                new OA\Property(property: 'machine_fingerprint', type: 'string'),
                new OA\Property(property: 'machine_name', type: 'string', nullable: true),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'released']),
                new OA\Property(property: 'last_ip', type: 'string', nullable: true),
                new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'released_at', type: 'string', format: 'date-time', nullable: true),
            ],
        )),
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/Activation'),
        ]))],
    )]
    public function update(UpdateActivationRequest $request, Activation $activation)
    {
        $activation->update($request->validated());
        $activation->load('licenseKey.product', 'licenseKey.customer');

        return ActivationResource::make($activation);
    }

    #[OA\Delete(
        path: '/api/admin/activations/{activation}',
        summary: 'Supprime définitivement une activation',
        security: [['sanctum' => []]],
        tags: ['Admin - Activations'],
        parameters: [new OA\Parameter(name: 'activation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimée')],
    )]
    public function destroy(Activation $activation)
    {
        $activation->delete();

        return response()->json(status: 204);
    }

    #[OA\Post(
        path: '/api/admin/activations/{activation}/release',
        summary: 'Libère un siège (transfert vers une autre machine)',
        security: [['sanctum' => []]],
        tags: ['Admin - Activations'],
        parameters: [new OA\Parameter(name: 'activation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Libérée', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Activation'),
            ])),
            new OA\Response(response: 409, description: "L'activation n'est pas active"),
        ],
    )]
    public function release(Activation $activation)
    {
        abort_if($activation->status !== 'active', 409, 'Cette activation n\'est pas active.');

        $activation->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        $activation->load('licenseKey.product', 'licenseKey.customer');

        return ActivationResource::make($activation);
    }

    #[OA\Post(
        path: '/api/admin/activations/{activation}/account-recovery-codes',
        summary: 'Génère un code de déblocage de compte local (usage unique)',
        description: "Le code en clair (`plaintext_code`) n'est présent qu'une seule fois dans cette réponse. "
            ."À communiquer par un canal vérifié (téléphone).",
        security: [['sanctum' => []]],
        tags: ['Admin - Activations'],
        parameters: [new OA\Parameter(name: 'activation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(
            properties: [new OA\Property(property: 'notes', type: 'string', nullable: true)],
        )),
        responses: [new OA\Response(response: 201, description: 'Créé', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'plaintext_code', type: 'string', example: 'CAHT4E6K'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        ]))],
    )]
    public function generateRecoveryCode(GenerateAccountRecoveryCodeRequest $request, Activation $activation)
    {
        $plaintextCode = $this->codeGenerator->generateUniqueCode();

        $recoveryCode = AccountRecoveryCode::query()->create([
            'activation_id' => $activation->id,
            'code_hash' => AccountRecoveryCodeGenerator::hash($plaintextCode),
            'created_by' => Auth::id(),
            'expires_at' => now()->addMinutes((int) config('license.account_recovery.ttl_minutes')),
            'notes' => $request->validated('notes'),
        ]);

        return response()->json([
            'plaintext_code' => $plaintextCode,
            'expires_at' => $recoveryCode->expires_at->toIso8601String(),
        ], 201);
    }
}
