<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key_last4' => $this->key_last4,
            'masked_key' => $this->maskedKey(),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'license_type' => $this->license_type,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'max_activations' => $this->max_activations,
            'active_activations_count' => $this->whenCounted('activeActivations'),
            'revoked' => $this->revoked,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_reason' => $this->revoked_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
