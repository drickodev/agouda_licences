<?php

namespace App\Console\Commands\License;

use App\Services\LicenseSigner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('license:signing-keys:generate {--force : Écraser les clés existantes sans confirmation}')]
#[Description('Génère la paire de clés Ed25519 de signature des réponses API')]
class GenerateSigningKeysCommand extends Command
{
    public function handle(LicenseSigner $signer): int
    {
        if (config('license.signing_private_key') && ! $this->option('force')) {
            if (! $this->confirm('Une clé privée de signature est déjà configurée dans .env. La remplacer ?')) {
                $this->info('Annulé.');

                return self::SUCCESS;
            }
        }

        $pair = $signer->generateKeyPair();

        $this->warn('Clé privée générée. Elle ne sera plus jamais affichée ici — copie-la dans .env immédiatement.');
        $this->newLine();
        $this->line('LICENSE_SIGNING_PRIVATE_KEY='.$pair['private']);
        $this->line('LICENSE_SIGNING_PUBLIC_KEY='.$pair['public']);
        $this->newLine();
        $this->info('Colle la clé publique ci-dessus dans le module client (Dart/Flutter). Ne commite jamais la clé privée.');

        return self::SUCCESS;
    }
}
