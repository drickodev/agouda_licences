<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AccountRecoveryCodeResource;
use App\Models\AccountRecoveryCode;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Codes de déblocage')]
class AccountRecoveryCodeController extends Controller
{
    #[OA\Get(
        path: '/api/admin/account-recovery-codes',
        summary: 'Liste les codes de déblocage de compte local (lecture seule)',
        security: [['sanctum' => []]],
        tags: ['Admin - Codes de déblocage'],
        parameters: [new OA\Parameter(name: 'used', in: 'query', schema: new OA\Schema(type: 'boolean'))],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
            properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AccountRecoveryCode'))],
        ))],
    )]
    public function index(Request $request)
    {
        $filters = $request->validate([
            'used' => ['nullable', 'boolean'],
        ]);

        $query = AccountRecoveryCode::query()->with('activation.licenseKey', 'createdBy');

        if (array_key_exists('used', $filters) && $filters['used'] !== null) {
            $filters['used']
                ? $query->whereNotNull('used_at')
                : $query->whereNull('used_at');
        }

        return AccountRecoveryCodeResource::collection($query->latest()->paginate());
    }
}
