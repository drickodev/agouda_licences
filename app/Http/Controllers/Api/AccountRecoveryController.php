<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RedeemAccountRecoveryCodeRequest;
use App\Services\AccountRecoveryService;
use Illuminate\Http\JsonResponse;

class AccountRecoveryController extends Controller
{
    public function __construct(private readonly AccountRecoveryService $accountRecovery)
    {
    }

    public function redeem(RedeemAccountRecoveryCodeRequest $request): JsonResponse
    {
        $result = $this->accountRecovery->redeem($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }
}
