<?php

namespace App\Services;

use App\Models\LicenseKey;

class LicenseKeyGenerator
{
    /**
     * Génère une clé unique au format XXXXX-XXXXX-XXXXX-XXXXX, garantie
     * absente en base. Le format est purement cosmétique : la sécurité
     * vient de l'imprévisibilité et du lookup en base, jamais d'un
     * algorithme de validation embarqué côté client.
     *
     * Seul le hash de la clé est persisté (§7.3.2) ; cette méthode renvoie
     * la valeur en clair, qui ne doit être affichée qu'une seule fois à
     * l'éditeur (terminal, notification admin) puis jamais reloguée.
     */
    public function generateUniqueKey(): string
    {
        do {
            $key = $this->generate();
        } while (LicenseKey::query()->where('key_hash', self::hash($key))->exists());

        return $key;
    }

    /**
     * Hash déterministe utilisé pour le stockage et le lookup en base.
     * SHA-256 (non un hash de mot de passe) : la clé a une entropie propre
     * très élevée (§7.2.2), un lookup direct par égalité suffit et reste
     * performant avec un index.
     */
    public static function hash(string $plaintextKey): string
    {
        return hash('sha256', $plaintextKey);
    }

    private function generate(): string
    {
        $alphabet = config('license.key_alphabet');
        $groups = config('license.key_groups');
        $groupLength = config('license.key_group_length');

        $segments = [];

        for ($g = 0; $g < $groups; $g++) {
            $segment = '';
            for ($i = 0; $i < $groupLength; $i++) {
                $segment .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $segments[] = $segment;
        }

        return implode('-', $segments);
    }
}
