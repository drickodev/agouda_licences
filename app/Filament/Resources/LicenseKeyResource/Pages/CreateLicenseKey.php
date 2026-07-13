<?php

namespace App\Filament\Resources\LicenseKeyResource\Pages;

use App\Filament\Resources\LicenseKeyResource;
use App\Services\LicenseKeyGenerator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLicenseKey extends CreateRecord
{
    protected static string $resource = LicenseKeyResource::class;

    private string $plaintextKey = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->plaintextKey = $data['key'];
        $data['key_hash'] = LicenseKeyGenerator::hash($data['key']);
        $data['key_last4'] = substr($data['key'], -4);
        unset($data['key']);

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Clé générée — copie-la maintenant')
            ->body("Seul le hash est stocké en base (§7.3.2) : cette valeur ne sera plus jamais affichée.\n\n{$this->plaintextKey}")
            ->success()
            ->persistent()
            ->send();
    }
}
