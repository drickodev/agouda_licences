<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActivateRequest;
use App\Http\Requests\Api\DeactivateRequest;
use App\Http\Requests\Api\ValidateLicenseRequest;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Licences (v1)')]
class LicenseController extends Controller
{
    public function __construct(private readonly LicenseService $licenses)
    {
    }

    #[OA\Post(
        path: '/api/v1/activate',
        summary: 'Active une licence sur la machine appelante',
        description: 'Idempotent : si la machine possède déjà une activation active pour cette clé, met simplement à jour `last_seen_at`. '
            .'Toute réponse (succès ou refus) est un payload signé Ed25519, jamais un simple booléen.',
        security: [['appToken' => []]],
        tags: ['Licences (v1)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['key', 'product_slug', 'machine_fingerprint', 'nonce'],
            properties: [
                new OA\Property(property: 'key', type: 'string', example: 'A7F3K-9M2XP-QW8ND-4RTVB'),
                new OA\Property(property: 'product_slug', type: 'string', example: 'agouda_syscohada'),
                new OA\Property(property: 'machine_fingerprint', type: 'string', example: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
                new OA\Property(property: 'machine_name', type: 'string', nullable: true, example: 'PC-Comptoir'),
                new OA\Property(property: 'nonce', type: 'string', minLength: 8, example: 'a1b2c3d4e5f6'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Payload signé (valid=true ou valid=false avec reason)', content: new OA\JsonContent(ref: '#/components/schemas/SignedPayload')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function activate(ActivateRequest $request): JsonResponse
    {
        $result = $this->licenses->activate($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }

    #[OA\Post(
        path: '/api/v1/validate',
        summary: 'Contrôle périodique de validité (phone-home)',
        description: "Vérifie qu'une activation active existe pour ce couple (clé, empreinte), que la clé n'est ni révoquée ni expirée. "
            .'Réponse toujours signée. Prévoir côté client une période de grâce longue en cas de coupure réseau.',
        security: [['appToken' => []]],
        tags: ['Licences (v1)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['key', 'machine_fingerprint', 'nonce'],
            properties: [
                new OA\Property(property: 'key', type: 'string'),
                new OA\Property(property: 'machine_fingerprint', type: 'string', example: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
                new OA\Property(property: 'nonce', type: 'string', minLength: 8),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Payload signé', content: new OA\JsonContent(ref: '#/components/schemas/SignedPayload')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function validateLicense(ValidateLicenseRequest $request): JsonResponse
    {
        $result = $this->licenses->validate($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }

    #[OA\Post(
        path: '/api/v1/deactivate',
        summary: 'Libère le siège occupé par la machine appelante',
        description: 'Permet à un client de transférer sa licence vers une autre machine.',
        security: [['appToken' => []]],
        tags: ['Licences (v1)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['key', 'machine_fingerprint', 'nonce'],
            properties: [
                new OA\Property(property: 'key', type: 'string'),
                new OA\Property(property: 'machine_fingerprint', type: 'string', example: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
                new OA\Property(property: 'nonce', type: 'string', minLength: 8),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Payload signé', content: new OA\JsonContent(ref: '#/components/schemas/SignedPayload')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function deactivate(DeactivateRequest $request): JsonResponse
    {
        $result = $this->licenses->deactivate($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }
}
