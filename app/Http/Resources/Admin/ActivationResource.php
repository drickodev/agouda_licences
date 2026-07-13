<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_key_id' => $this->license_key_id,
            'license_key' => LicenseKeyResource::make($this->whenLoaded('licenseKey')),
            'machine_fingerprint' => $this->machine_fingerprint,
            'machine_name' => $this->machine_name,
            'status' => $this->status,
            'last_ip' => $this->last_ip,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
