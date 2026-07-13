<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Auth')]
class AuthController extends Controller
{
    /**
     * Durée de vie du challenge 2FA (le temps de saisir le code TOTP après
     * un login mot de passe correct).
     */
    private const TWO_FACTOR_CHALLENGE_TTL_MINUTES = 5;

    #[OA\Post(
        path: '/api/admin/login',
        summary: 'Connexion administrateur',
        description: "Renvoie un token Sanctum directement, ou `two_factor_required: true` + un `challenge_token` "
            ."si le compte a le 2FA activé (voir POST /api/admin/2fa/challenge).",
        tags: ['Admin - Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Token délivré ou challenge 2FA requis', content: new OA\JsonContent(
                oneOf: [
                    new OA\Schema(properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'user', type: 'object'),
                    ]),
                    new OA\Schema(properties: [
                        new OA\Property(property: 'two_factor_required', type: 'boolean', example: true),
                        new OA\Property(property: 'challenge_token', type: 'string'),
                    ]),
                ],
            )),
            new OA\Response(response: 422, description: 'Identifiants invalides', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $challengeToken = Str::random(40);

            Cache::put(
                "2fa_challenge:{$challengeToken}",
                $user->id,
                now()->addMinutes(self::TWO_FACTOR_CHALLENGE_TTL_MINUTES),
            );

            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

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

    #[OA\Post(
        path: '/api/admin/logout',
        summary: 'Révoque le token courant',
        security: [['sanctum' => []]],
        tags: ['Admin - Auth'],
        responses: [new OA\Response(response: 204, description: 'Déconnecté')],
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    #[OA\Get(
        path: '/api/admin/me',
        summary: "Profil de l'utilisateur connecté",
        security: [['sanctum' => []]],
        tags: ['Admin - Auth'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string'),
        ]))],
    )]
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
