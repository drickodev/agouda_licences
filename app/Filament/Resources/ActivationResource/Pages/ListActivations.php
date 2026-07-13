<?php

namespace App\Filament\Resources\ActivationResource\Pages;

use App\Filament\Resources\ActivationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivations extends ListRecords
{
    protected static string $resource = ActivationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
