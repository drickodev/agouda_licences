<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_key_id' => $this->license_key_id,
            'license_key' => LicenseKeyResource::make($this->whenLoaded('licenseKey')),
            'event' => $this->event,
            'machine_fingerprint' => $this->machine_fingerprint,
            'ip' => $this->ip,
            'success' => $this->success,
            'reason' => $this->reason,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
