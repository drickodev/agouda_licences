<?php

namespace App\Services;

use App\Models\AccountRecoveryCode;

class AccountRecoveryCodeGenerator
{
    /**
     * Génère un code court à usage unique, garanti absent en base (parmi les
     * codes non encore expirés/utilisés — la fenêtre de validité étant
     * courte, l'espace de codes se libère naturellement).
     */
    public function generateUniqueCode(): string
    {
        do {
            $code = $this->generate();
        } while (AccountRecoveryCode::query()->where('code_hash', self::hash($code))->exists());

        return $code;
    }

    private function generate(): string
    {
        $alphabet = config('license.account_recovery.code_alphabet');
        $length = config('license.account_recovery.code_length');

        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    public static function hash(string $plaintextCode): string
    {
        return hash('sha256', strtoupper($plaintextCode));
    }
}
