<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activation extends Model
{
    protected $fillable = [
        'license_key_id',
        'machine_fingerprint',
        'machine_name',
        'status',
        'last_ip',
        'last_seen_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function licenseKey(): BelongsTo
    {
        return $this->belongsTo(LicenseKey::class);
    }

    public function accountRecoveryCodes(): HasMany
    {
        return $this->hasMany(AccountRecoveryCode::class);
    }
}
