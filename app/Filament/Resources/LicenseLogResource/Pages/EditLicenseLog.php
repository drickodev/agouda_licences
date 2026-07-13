<?php

namespace App\Filament\Resources\LicenseLogResource\Pages;

use App\Filament\Resources\LicenseLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLicenseLog extends EditRecord
{
    protected static string $resource = LicenseLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
