<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Auth')]
class PasswordResetController extends Controller
{
    #[OA\Post(
        path: '/api/admin/password/forgot',
        summary: 'Demande un code de réinitialisation de mot de passe',
        description: "Envoie par email un code à saisir dans l'app avec le nouveau mot de passe (voir POST /api/admin/password/reset). "
            .'Répond toujours avec le même message, que l\'email corresponde ou non à un compte, pour ne pas révéler son existence.',
        tags: ['Admin - Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Email envoyé si le compte existe', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string'),
            ])),
        ],
    )]
    public function forgot(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($data);

        return response()->json([
            'message' => "Si un compte existe avec cet email, un code de réinitialisation vient d'être envoyé.",
        ]);
    }

    #[OA\Post(
        path: '/api/admin/password/reset',
        summary: 'Réinitialise le mot de passe avec le code reçu par email',
        description: 'Révoque tous les tokens Sanctum existants du compte une fois le mot de passe changé (déconnexion de toutes les sessions).',
        tags: ['Admin - Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'token', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe réinitialisé'),
            new OA\Response(response: 422, description: 'Code invalide ou expiré', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            // Message générique : ne pas révéler si c'est l'email ou le
            // token qui est en cause (évite l'énumération de comptes).
            throw ValidationException::withMessages([
                'token' => ['Ce code est invalide ou expiré.'],
            ]);
        }

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }
}
