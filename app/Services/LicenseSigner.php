<?php

namespace App\Services;

use RuntimeException;

class LicenseSigner
{
    /**
     * Signe un payload avec la clé privée Ed25519 du serveur.
     *
     * Convention : `payload` est renvoyé comme la chaîne JSON brute exacte
     * qui a été signée (et non l'objet décodé). Le client doit vérifier la
     * signature sur ces octets précis avant de parser le JSON, sans quoi
     * une re-sérialisation peut réordonner les clés et invalider une
     * signature pourtant correcte.
     *
     * @return array{payload: string, signature: string}
     */
    public function sign(array $payload): array
    {
        $privateKey = $this->privateKey();

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = sodium_crypto_sign_detached($json, $privateKey);

        return [
            'payload' => $json,
            'signature' => base64_encode($signature),
        ];
    }

    /**
     * Génère une nouvelle paire de clés Ed25519, encodées en base64.
     *
     * @return array{public: string, private: string}
     */
    public function generateKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
            'private' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
        ];
    }

    private function privateKey(): string
    {
        $encoded = config('license.signing_private_key');

        if (empty($encoded)) {
            throw new RuntimeException(
                'Aucune clé privée de signature configurée. Exécutez: php artisan license:signing-keys:generate'
            );
        }

        return base64_decode($encoded);
    }
}
