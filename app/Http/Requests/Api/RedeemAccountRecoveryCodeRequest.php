<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RedeemAccountRecoveryCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:16'],
            'machine_fingerprint' => ['required', 'string', 'max:255', 'regex:/^sha256:[a-f0-9]{64}$/i'],
            'nonce' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
