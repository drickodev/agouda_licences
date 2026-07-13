<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseLog extends Model
{
    protected $fillable = [
        'license_key_id',
        'event',
        'machine_fingerprint',
        'ip',
        'success',
        'reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function licenseKey(): BelongsTo
    {
        return $this->belongsTo(LicenseKey::class);
    }
}
