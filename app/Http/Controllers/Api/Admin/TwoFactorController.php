<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * 2FA de l'API admin (colonnes `two_factor_secret` / `two_factor_confirmed_at`
 * sur User, TOTP via pragmarx/google2fa-qrcode).
 */
#[OA\Tag(name: 'Admin - 2FA')]
class TwoFactorController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa)
    {
    }

    #[OA\Get(
        path: '/api/admin/2fa',
        summary: 'Statut du 2FA pour le compte connecté',
        security: [['sanctum' => []]],
        tags: ['Admin - 2FA'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'enabled', type: 'boolean'),
            new OA\Property(property: 'pending', type: 'boolean', description: 'Secret généré mais pas encore confirmé'),
        ]))],
    )]
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
        ]);
    }

    /**
     * Génère (ou réutilise) un secret TOTP en attente de confirmation, et
     * renvoie l'URI otpauth:// permettant à l'app d'afficher un QR code.
     */
    #[OA\Post(
        path: '/api/admin/2fa/setup',
        summary: 'Initialise le 2FA (génère un secret TOTP + QR code)',
        security: [['sanctum' => []]],
        tags: ['Admin - 2FA'],
        responses: [
            new OA\Response(response: 200, description: 'Secret généré', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'secret', type: 'string', example: 'QXQD7WEM5DZ65NCGYEDHMSK2B3ISJAYS'),
                new OA\Property(property: 'otpauth_url', type: 'string', example: 'otpauth://totp/API%20Licences:admin@example.com?secret=...'),
            ])),
            new OA\Response(response: 422, description: '2FA déjà activé', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function setup(Request $request)
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => ['Le 2FA est déjà activé sur ce compte.'],
            ]);
        }

        $secret = $user->two_factor_secret ?? $this->google2fa->generateSecretKey();

        if ($user->two_factor_secret === null) {
            $user->forceFill(['two_factor_secret' => $secret])->save();
        }

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
        ]);
    }

    #[OA\Post(
        path: '/api/admin/2fa/enable',
        summary: 'Confirme le 2FA avec un premier code TOTP',
        security: [['sanctum' => []]],
        tags: ['Admin - 2FA'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code'],
            properties: [new OA\Property(property: 'code', type: 'string', example: '123456')],
        )),
        responses: [
            new OA\Response(response: 200, description: '2FA activé', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'enabled', type: 'boolean', example: true),
            ])),
            new OA\Response(response: 422, description: 'Code invalide', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function enable(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                'code' => ['Initialisez d\'abord le 2FA avant de le confirmer.'],
            ]);
        }

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['Code invalide.']]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return response()->json(['enabled' => true]);
    }

    #[OA\Post(
        path: '/api/admin/2fa/disable',
        summary: 'Désactive le 2FA (nécessite un code TOTP valide)',
        security: [['sanctum' => []]],
        tags: ['Admin - 2FA'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code'],
            properties: [new OA\Property(property: 'code', type: 'string', example: '123456')],
        )),
        responses: [
            new OA\Response(response: 200, description: '2FA désactivé', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'enabled', type: 'boolean', example: false),
            ])),
            new OA\Response(response: 422, description: 'Code invalide', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function disable(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled() || ! $this->google2fa->verifyKey($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['Code invalide.']]);
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        return response()->json(['enabled' => false]);
    }

    /**
     * Deuxième étape du login : échange un challenge_token (émis par
     * AuthController::login lorsque le compte a le 2FA actif) contre un
     * vrai token Sanctum, après vérification du code TOTP.
     */
    #[OA\Post(
        path: '/api/admin/2fa/challenge',
        summary: "Deuxième étape du login : échange un challenge_token contre un token",
        tags: ['Admin - 2FA'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['challenge_token', 'code'],
            properties: [
                new OA\Property(property: 'challenge_token', type: 'string'),
                new OA\Property(property: 'code', type: 'string', example: '123456'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Token délivré', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'user', type: 'object'),
            ])),
            new OA\Response(response: 422, description: 'Code ou challenge invalide/expiré', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function challenge(Request $request)
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $cacheKey = "2fa_challenge:{$data['challenge_token']}";
        $userId = Cache::get($cacheKey);

        if ($userId === null) {
            throw ValidationException::withMessages([
                'challenge_token' => ['Challenge expiré ou invalide, reconnectez-vous.'],
            ]);
        }

        $user = User::query()->findOrFail($userId);

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['Code invalide.']]);
        }

        Cache::forget($cacheKey);

        $token = $user->createToken('flutter-admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
