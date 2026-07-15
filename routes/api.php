<?php

use App\Http\Controllers\Api\AccountRecoveryController;
use App\Http\Controllers\Api\Admin as Admin;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Middleware\RestrictAdminByIp;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['app.token'])
    ->group(function () {
        Route::middleware('throttle:license')->group(function () {
            Route::post('/activate', [LicenseController::class, 'activate']);
            Route::post('/validate', [LicenseController::class, 'validateLicense']);
            Route::post('/deactivate', [LicenseController::class, 'deactivate']);
        });

        // Endpoint sensible (code court, à usage unique) : rate limit dédié,
        // plus strict que activate/validate.
        Route::middleware('throttle:account-recovery')->group(function () {
            Route::post('/account-recovery/redeem', [AccountRecoveryController::class, 'redeem']);
        });
    });

// Face ADMIN (API) : consommée par l'app Flutter d'administration.
// Authentification par token Sanctum (pas de session/cookie), IP
// restreinte par liste blanche (ADMIN_ALLOWED_IPS).
Route::prefix('admin')->group(function () {
    Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:6,1');

    // Deuxième étape du login (code TOTP), non authentifiée puisque le
    // token Sanctum n'est délivré qu'à l'issue de cette vérification.
    Route::post('/2fa/challenge', [Admin\TwoFactorController::class, 'challenge'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', RestrictAdminByIp::class])->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'logout']);
        Route::get('/me', [Admin\AuthController::class, 'me']);

        Route::get('dashboard', [Admin\DashboardController::class, 'index']);

        Route::get('2fa', [Admin\TwoFactorController::class, 'status']);
        Route::post('2fa/setup', [Admin\TwoFactorController::class, 'setup']);
        Route::post('2fa/enable', [Admin\TwoFactorController::class, 'enable']);
        Route::post('2fa/disable', [Admin\TwoFactorController::class, 'disable']);

        Route::apiResource('products', Admin\ProductController::class);

        // Routes sensibles (§audit sécurité, point 4) : 2FA obligatoire, pas
        // seulement un token Sanctum valide.
        Route::middleware('2fa.required')->group(function () {
            Route::apiResource('customers', Admin\CustomerController::class);

            Route::apiResource('license-keys', Admin\LicenseKeyController::class);
            Route::post('license-keys/{license_key}/revoke', [Admin\LicenseKeyController::class, 'revoke']);

            // Re-confirmation 2FA immédiate en plus du 2FA obligatoire
            // ci-dessus (§audit sécurité, point 3) : throttle dédié, plus
            // strict que le reste du groupe admin.
            Route::post('license-keys/{license_key}/reveal', [Admin\LicenseKeyController::class, 'reveal'])
                ->middleware('throttle:license-key-reveal');
        });

        Route::apiResource('activations', Admin\ActivationController::class);
        Route::post('activations/{activation}/release', [Admin\ActivationController::class, 'release']);
        Route::post('activations/{activation}/account-recovery-codes', [Admin\ActivationController::class, 'generateRecoveryCode']);

        Route::get('license-logs', [Admin\LicenseLogController::class, 'index']);
        Route::get('account-recovery-codes', [Admin\AccountRecoveryCodeController::class, 'index']);
    });
});
