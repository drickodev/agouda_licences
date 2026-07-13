<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLicenseKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'license_type' => ['required', 'in:perpetual,subscription'],
            'expires_at' => ['required_if:license_type,subscription', 'nullable', 'date'],
            'max_activations' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'revoked' => ['required', 'boolean'],
            'revoked_at' => ['required_if:revoked,1', 'nullable', 'date'],
            'revoked_reason' => ['required_if:revoked,1', 'nullable', 'string', 'max:255'],
        ];
    }
}
