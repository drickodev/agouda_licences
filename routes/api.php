<?php

use App\Http\Controllers\Api\AccountRecoveryController;
use App\Http\Controllers\Api\LicenseController;
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
