<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexLicenseKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plaintext_key' => ['nullable', 'string'],
            'product_id' => ['nullable', 'exists:products,id'],
            'license_type' => ['nullable', 'in:perpetual,subscription'],
            'revoked' => ['nullable', 'boolean'],
        ];
    }
}
