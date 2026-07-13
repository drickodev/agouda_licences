<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RedeemAccountRecoveryCodeRequest;
use App\Services\AccountRecoveryService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Licences (v1)')]
class AccountRecoveryController extends Controller
{
    public function __construct(private readonly AccountRecoveryService $accountRecovery)
    {
    }

    #[OA\Post(
        path: '/api/v1/account-recovery/redeem',
        summary: 'Consomme un code de déblocage de compte local',
        description: "Sans rapport avec les licences : déblocage d'un compte utilisateur stocké localement dans le logiciel client. "
            .'Refuse si code introuvable/déjà utilisé/expiré, ou si empreinte machine différente de celle de l\'activation liée au code.',
        security: [['appToken' => []]],
        tags: ['Licences (v1)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code', 'machine_fingerprint', 'nonce'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: '7F3KQW8N'),
                new OA\Property(property: 'machine_fingerprint', type: 'string', example: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
                new OA\Property(property: 'nonce', type: 'string', minLength: 8),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Payload signé', content: new OA\JsonContent(ref: '#/components/schemas/SignedPayload')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function redeem(RedeemAccountRecoveryCodeRequest $request): JsonResponse
    {
        $result = $this->accountRecovery->redeem($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }
}
