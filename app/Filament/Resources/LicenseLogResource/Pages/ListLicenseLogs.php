<?php

namespace App\Filament\Resources\LicenseLogResource\Pages;

use App\Filament\Resources\LicenseLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLicenseLogs extends ListRecords
{
    protected static string $resource = LicenseLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
