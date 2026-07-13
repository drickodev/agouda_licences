<?php

namespace App\Models;

use App\Services\AccountRecoveryCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountRecoveryCode extends Model
{
    protected $fillable = [
        'activation_id',
        'code_hash',
        'created_by',
        'expires_at',
        'used_at',
        'used_from_ip',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(Activation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeWherePlaintextCode(Builder $query, string $plaintextCode): Builder
    {
        return $query->where('code_hash', AccountRecoveryCodeGenerator::hash($plaintextCode));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isRedeemable(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }
}
