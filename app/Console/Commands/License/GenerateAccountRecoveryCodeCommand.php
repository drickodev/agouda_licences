<?php

namespace App\Console\Commands\License;

use App\Models\AccountRecoveryCode;
use App\Models\Activation;
use App\Services\AccountRecoveryCodeGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('license:account-recovery:generate {activation_id : ID de l\'activation (machine) concernée} {--notes= : Motif, ex. raison de l\'appel du client}')]
#[Description('Génère un code de déblocage de compte local (mot de passe oublié) pour une activation précise')]
class GenerateAccountRecoveryCodeCommand extends Command
{
    public function handle(AccountRecoveryCodeGenerator $generator): int
    {
        $activation = Activation::query()->find($this->argument('activation_id'));

        if (! $activation) {
            $this->error("Activation introuvable pour l'ID {$this->argument('activation_id')}.");

            return self::FAILURE;
        }

        $plaintext = $generator->generateUniqueCode();
        $ttlMinutes = (int) config('license.account_recovery.ttl_minutes');

        AccountRecoveryCode::query()->create([
            'activation_id' => $activation->id,
            'code_hash' => AccountRecoveryCodeGenerator::hash($plaintext),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'notes' => $this->option('notes'),
        ]);

        $this->warn("Machine : {$activation->machine_name} (empreinte {$activation->machine_fingerprint}).");
        $this->warn("Ce code ne sera plus jamais affiché. Communique-le au client par un canal vérifié (téléphone, pas email non chiffré).");
        $this->newLine();
        $this->info("Code : {$plaintext}");
        $this->line("Valable {$ttlMinutes} minutes, usage unique, utilisable uniquement sur cette machine.");

        return self::SUCCESS;
    }
}
