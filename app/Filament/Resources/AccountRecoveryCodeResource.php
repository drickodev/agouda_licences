<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountRecoveryCodeResource\Pages;
use App\Models\AccountRecoveryCode;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountRecoveryCodeResource extends Resource
{
    protected static ?string $model = AccountRecoveryCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Déblocages de compte';

    protected static ?string $modelLabel = 'code de déblocage';

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
                    ->label('Généré le')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activation.machine_name')
                    ->label('Machine')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('activation.licenseKey.key_last4')
                    ->label('Clé')
                    ->formatStateUsing(fn (?string $state) => $state ? "••••-••••-••••-{$state}" : null)
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Généré par')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('used_at')
                    ->label('Utilisé')
                    ->boolean()
                    ->getStateUsing(fn (AccountRecoveryCode $record) => $record->isUsed())
                    ->trueColor('gray')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('used_from_ip')
                    ->label('IP d\'utilisation')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('used_at')
                    ->label('Utilisé')
                    ->nullable()
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('used_at'),
                        false: fn ($query) => $query->whereNull('used_at'),
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountRecoveryCodes::route('/'),
        ];
    }
}
