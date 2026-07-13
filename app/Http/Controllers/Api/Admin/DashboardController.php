<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LicenseLogResource;
use App\Models\Activation;
use App\Models\Customer;
use App\Models\LicenseKey;
use App\Models\LicenseLog;
use App\Models\Product;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Dashboard')]
class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/admin/dashboard',
        summary: "Statistiques agrégées pour l'écran d'accueil",
        security: [['sanctum' => []]],
        tags: ['Admin - Dashboard'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'products', type: 'object', properties: [
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'active', type: 'integer'),
            ]),
            new OA\Property(property: 'customers', type: 'object', properties: [
                new OA\Property(property: 'total', type: 'integer'),
            ]),
            new OA\Property(property: 'license_keys', type: 'object', properties: [
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'active', type: 'integer'),
                new OA\Property(property: 'revoked', type: 'integer'),
                new OA\Property(property: 'expiring_soon', type: 'integer', description: 'Abonnements expirant dans les 30 jours'),
            ]),
            new OA\Property(property: 'activations', type: 'object', properties: [
                new OA\Property(property: 'active', type: 'integer'),
            ]),
            new OA\Property(property: 'anomalies_last_7_days', type: 'integer'),
            new OA\Property(property: 'recent_logs', type: 'array', items: new OA\Items(ref: '#/components/schemas/LicenseLog')),
        ]))],
    )]
    public function index()
    {
        $totalKeys = LicenseKey::query()->count();
        $revokedKeys = LicenseKey::query()->where('revoked', true)->count();

        return response()->json([
            'products' => [
                'total' => Product::query()->count(),
                'active' => Product::query()->where('active', true)->count(),
            ],
            'customers' => [
                'total' => Customer::query()->count(),
            ],
            'license_keys' => [
                'total' => $totalKeys,
                'active' => $totalKeys - $revokedKeys,
                'revoked' => $revokedKeys,
                'expiring_soon' => LicenseKey::query()
                    ->where('revoked', false)
                    ->where('license_type', 'subscription')
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    ->count(),
            ],
            'activations' => [
                'active' => Activation::query()->where('status', 'active')->count(),
            ],
            'anomalies_last_7_days' => LicenseLog::query()
                ->where('event', 'anomaly_detected')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'recent_logs' => LicenseLogResource::collection(
                LicenseLog::query()->with('licenseKey')->latest()->limit(10)->get()
            ),
        ]);
    }
}
