<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActivateRequest extends FormRequest
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
            'product_slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/'],
            'machine_fingerprint' => ['required', 'string', 'max:255', 'regex:/^sha256:[a-f0-9]{64}$/i'],
            'machine_name' => ['nullable', 'string', 'max:120'],
            'nonce' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
