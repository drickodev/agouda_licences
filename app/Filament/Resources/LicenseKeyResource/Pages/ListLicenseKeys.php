<?php

namespace App\Filament\Resources\LicenseKeyResource\Pages;

use App\Filament\Resources\LicenseKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLicenseKeys extends ListRecords
{
    protected static string $resource = LicenseKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
