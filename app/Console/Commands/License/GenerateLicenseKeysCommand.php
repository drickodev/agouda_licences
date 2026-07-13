<?php

namespace App\Console\Commands\License;

use App\Models\Customer;
use App\Models\LicenseKey;
use App\Models\Product;
use App\Services\LicenseKeyGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature(<<<'SIG'
    license:generate
        {product : Slug du produit}
        {count=1 : Nombre de clés à générer}
        {--seats=1 : Nombre de machines autorisées par clé (max_activations)}
        {--type=perpetual : perpetual ou subscription}
        {--expires= : Date d'expiration (YYYY-MM-DD), requise si type=subscription}
        {--customer= : ID du client à associer (optionnel)}
    SIG)]
#[Description('Génère un lot de clés de licence pour un produit')]
class GenerateLicenseKeysCommand extends Command
{
    public function handle(LicenseKeyGenerator $generator): int
    {
        $product = Product::query()->where('slug', $this->argument('product'))->first();

        if (! $product) {
            $this->error("Produit introuvable pour le slug « {$this->argument('product')} ».");

            return self::FAILURE;
        }

        $count = (int) $this->argument('count');
        $type = $this->option('type');

        if (! in_array($type, ['perpetual', 'subscription'], true)) {
            $this->error('--type doit être "perpetual" ou "subscription".');

            return self::FAILURE;
        }

        $expiresAt = null;
        if ($type === 'subscription') {
            if (! $this->option('expires')) {
                $this->error('--expires est requis pour une licence de type subscription.');

                return self::FAILURE;
            }
            $expiresAt = Carbon::parse($this->option('expires'))->endOfDay();
        }

        $customerId = $this->option('customer');
        if ($customerId && ! Customer::query()->whereKey($customerId)->exists()) {
            $this->error("Client introuvable pour l'ID {$customerId}.");

            return self::FAILURE;
        }

        $generated = [];

        for ($i = 0; $i < $count; $i++) {
            $plaintext = $generator->generateUniqueKey();

            LicenseKey::query()->create([
                'key_hash' => LicenseKeyGenerator::hash($plaintext),
                'key_last4' => substr($plaintext, -4),
                'product_id' => $product->id,
                'customer_id' => $customerId,
                'license_type' => $type,
                'expires_at' => $expiresAt,
                'max_activations' => (int) $this->option('seats'),
            ]);

            $generated[] = $plaintext;
        }

        $this->warn('Seul le hash de chaque clé est stocké en base (§7.3.2) : ces valeurs en clair ne seront plus jamais affichées. Transmets-les au client maintenant.');
        $this->info("{$count} clé(s) générée(s) pour « {$product->name} » :");
        $this->newLine();
        foreach ($generated as $key) {
            $this->line($key);
        }

        return self::SUCCESS;
    }
}
