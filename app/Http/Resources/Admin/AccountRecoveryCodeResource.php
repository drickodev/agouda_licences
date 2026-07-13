<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountRecoveryCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activation' => ActivationResource::make($this->whenLoaded('activation')),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'is_used' => $this->isUsed(),
            'used_from_ip' => $this->used_from_ip,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
