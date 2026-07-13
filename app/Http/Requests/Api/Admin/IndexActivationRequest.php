<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:active,released'],
            'license_key_id' => ['nullable', 'exists:license_keys,id'],
        ];
    }
}
