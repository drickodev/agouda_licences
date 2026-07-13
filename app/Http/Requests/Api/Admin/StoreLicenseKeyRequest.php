<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLicenseKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:64'],
            'product_id' => ['required', 'exists:products,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'license_type' => ['required', 'in:perpetual,subscription'],
            'expires_at' => ['required_if:license_type,subscription', 'nullable', 'date'],
            'max_activations' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
