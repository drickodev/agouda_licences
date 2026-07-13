<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActivateRequest;
use App\Http\Requests\Api\DeactivateRequest;
use App\Http\Requests\Api\ValidateLicenseRequest;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;

class LicenseController extends Controller
{
    public function __construct(private readonly LicenseService $licenses)
    {
    }

    public function activate(ActivateRequest $request): JsonResponse
    {
        $result = $this->licenses->activate($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }

    public function validateLicense(ValidateLicenseRequest $request): JsonResponse
    {
        $result = $this->licenses->validate($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }

    public function deactivate(DeactivateRequest $request): JsonResponse
    {
        $result = $this->licenses->deactivate($request, $request->validated());

        return response()->json($result['signed'], $result['status']);
    }
}
