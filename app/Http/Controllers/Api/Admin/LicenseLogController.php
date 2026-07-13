<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LicenseLogResource;
use App\Models\LicenseLog;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Logs')]
class LicenseLogController extends Controller
{
    #[OA\Get(
        path: '/api/admin/license-logs',
        summary: 'Liste les logs de licence (lecture seule)',
        security: [['sanctum' => []]],
        tags: ['Admin - Logs'],
        parameters: [
            new OA\Parameter(name: 'event', in: 'query', schema: new OA\Schema(type: 'string', example: 'activate')),
            new OA\Parameter(name: 'success', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'license_key_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
            properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LicenseLog'))],
        ))],
    )]
    public function index(Request $request)
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string'],
            'success' => ['nullable', 'boolean'],
            'license_key_id' => ['nullable', 'exists:license_keys,id'],
        ]);

        $query = LicenseLog::query()->with('licenseKey');

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (array_key_exists('success', $filters) && $filters['success'] !== null) {
            $query->where('success', (bool) $filters['success']);
        }

        if (! empty($filters['license_key_id'])) {
            $query->where('license_key_id', $filters['license_key_id']);
        }

        return LicenseLogResource::collection($query->latest()->paginate());
    }
}
