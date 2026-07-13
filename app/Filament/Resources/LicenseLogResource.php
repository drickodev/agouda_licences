<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LicenseLogResource\Pages;
use App\Models\LicenseLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LicenseLogResource extends Resource
{
    protected static ?string $model = LicenseLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Journal';

    protected static ?string $modelLabel = 'entrée de journal';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Horodatage')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('licenseKey.key_last4')
                    ->label('Clé')
                    ->formatStateUsing(fn (?string $state) => $state ? "••••-••••-••••-{$state}" : null)
                    ->fontFamily('mono')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('event')
                    ->badge(),
                Tables\Columns\IconColumn::make('success')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('reason')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('machine_fingerprint')
                    ->limit(24)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'activate' => 'activate',
                        'validate' => 'validate',
                        'deactivate' => 'deactivate',
                        'anomaly_detected' => 'anomaly_detected',
                    ]),
                Tables\Filters\TernaryFilter::make('success')
                    ->label('Succès'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLicenseLogs::route('/'),
        ];
    }
}
