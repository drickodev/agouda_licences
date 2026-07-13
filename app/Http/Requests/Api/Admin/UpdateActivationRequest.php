<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_key_id' => ['required', 'exists:license_keys,id'],
            'machine_fingerprint' => ['required', 'string', 'max:255'],
            'machine_name' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,released'],
            'last_ip' => ['nullable', 'ip'],
            'last_seen_at' => ['nullable', 'date'],
            'released_at' => ['nullable', 'date'],
        ];
    }
}
