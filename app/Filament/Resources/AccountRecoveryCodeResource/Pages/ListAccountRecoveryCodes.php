<?php

namespace App\Filament\Resources\AccountRecoveryCodeResource\Pages;

use App\Filament\Resources\AccountRecoveryCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountRecoveryCodes extends ListRecords
{
    protected static string $resource = AccountRecoveryCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
