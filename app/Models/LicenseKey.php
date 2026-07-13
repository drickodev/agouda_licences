<?php

namespace App\Models;

use App\Services\LicenseKeyGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseKey extends Model
{
    protected $fillable = [
        'key_hash',
        'key_last4',
        'product_id',
        'customer_id',
        'license_type',
        'expires_at',
        'max_activations',
        'revoked',
        'revoked_at',
        'revoked_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
            'revoked_at' => 'datetime',
            'max_activations' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    public function activeActivations(): HasMany
    {
        return $this->activations()->where('status', 'active');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LicenseLog::class);
    }

    public function isExpired(): bool
    {
        return $this->license_type === 'subscription'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function activationFor(string $machineFingerprint): ?Activation
    {
        return $this->activeActivations()
            ->where('machine_fingerprint', $machineFingerprint)
            ->first();
    }

    public function hasFreeSeat(): bool
    {
        return $this->activeActivations()->count() < $this->max_activations;
    }

    /**
     * Recherche par clé en clair : hache l'entrée et compare au key_hash
     * stocké. C'est l'unique point d'entrée pour retrouver une clé à partir
     * de sa valeur fournie par un client ou un administrateur.
     */
    public function scopeWherePlaintextKey(Builder $query, string $plaintextKey): Builder
    {
        return $query->where('key_hash', LicenseKeyGenerator::hash($plaintextKey));
    }

    public function maskedKey(): string
    {
        return '••••-••••-••••-'.$this->key_last4;
    }
}
