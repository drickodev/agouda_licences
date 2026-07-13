<?php

namespace App\Console\Commands\License;

use App\Models\LicenseKey;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('license:revoke {key : La clé de licence à révoquer (valeur en clair)} {--reason= : Motif de la révocation}')]
#[Description('Révoque une clé de licence (fuite, impayé, fraude)')]
class RevokeLicenseKeyCommand extends Command
{
    public function handle(): int
    {
        $key = LicenseKey::query()->wherePlaintextKey($this->argument('key'))->first();

        if (! $key) {
            $this->error('Clé introuvable.');

            return self::FAILURE;
        }

        if ($key->revoked) {
            $this->warn('Cette clé est déjà révoquée.');

            return self::SUCCESS;
        }

        $key->update([
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_reason' => $this->option('reason'),
        ]);

        $this->info("Clé se terminant par {$key->key_last4} révoquée.");

        return self::SUCCESS;
    }
}
