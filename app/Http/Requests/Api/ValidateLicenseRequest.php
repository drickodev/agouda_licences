<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidateLicenseRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:64'],
            'machine_fingerprint' => ['required', 'string', 'max:255', 'regex:/^sha256:[a-f0-9]{64}$/i'],
            'nonce' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
