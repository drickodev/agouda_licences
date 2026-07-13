<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LicenseKeyResource\Pages;
use App\Models\LicenseKey;
use App\Services\LicenseKeyGenerator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LicenseKeyResource extends Resource
{
    protected static ?string $model = LicenseKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Clés de licence';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label('Clé (affichée une seule fois)')
                    ->required()
                    ->maxLength(64)
                    ->default(fn () => app(LicenseKeyGenerator::class)->generateUniqueKey())
                    ->dehydrated(fn (string $operation) => $operation === 'create')
                    ->visible(fn (string $operation) => $operation === 'create')
                    ->helperText('Générée automatiquement ; imprévisible, jamais dérivée d\'un algorithme. Seul son hash est stocké : copie-la après création, elle ne sera plus jamais visible.'),
                Forms\Components\TextInput::make('key_last4')
                    ->label('Clé')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (?string $state) => $state ? "••••-••••-••••-{$state}" : null)
                    ->visible(fn (string $operation) => $operation === 'edit'),
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('license_type')
                    ->options([
                        'perpetual' => 'Perpétuelle',
                        'subscription' => 'Abonnement',
                    ])
                    ->required()
                    ->live(),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Date d\'expiration')
                    ->required(fn (Forms\Get $get) => $get('license_type') === 'subscription')
                    ->visible(fn (Forms\Get $get) => $get('license_type') === 'subscription'),
                Forms\Components\TextInput::make('max_activations')
                    ->label('Sièges autorisés')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                Forms\Components\Toggle::make('revoked')
                    ->label('Révoquée')
                    ->live(),
                Forms\Components\DateTimePicker::make('revoked_at')
                    ->visible(fn (Forms\Get $get) => (bool) $get('revoked')),
                Forms\Components\TextInput::make('revoked_reason')
                    ->label('Motif de révocation')
                    ->visible(fn (Forms\Get $get) => (bool) $get('revoked')),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key_last4')
                    ->label('Clé')
                    ->formatStateUsing(fn (string $state) => "••••-••••-••••-{$state}")
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('product.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('license_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'perpetual' ? 'Perpétuelle' : 'Abonnement')
                    ->color(fn (string $state) => $state === 'perpetual' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('max_activations')
                    ->label('Sièges')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_activations_count')
                    ->label('Actives')
                    ->counts('activeActivations')
                    ->badge(),
                Tables\Columns\IconColumn::make('revoked')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('plaintext_key')
                    ->label('Rechercher par clé complète')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Clé complète fournie par le client'),
                    ])
                    ->query(fn ($query, array $data) => $data['value']
                        ? $query->wherePlaintextKey($data['value'])
                        : $query)
                    ->indicateUsing(fn (array $data) => $data['value'] ? 'Clé : '.$data['value'] : null),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name'),
                Tables\Filters\SelectFilter::make('license_type')
                    ->label('Type')
                    ->options([
                        'perpetual' => 'Perpétuelle',
                        'subscription' => 'Abonnement',
                    ]),
                Tables\Filters\TernaryFilter::make('revoked')
                    ->label('Révoquée'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (LicenseKey $record) => ! $record->revoked)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('reason')
                            ->label('Motif')
                            ->required(),
                    ])
                    ->action(function (LicenseKey $record, array $data) {
                        $record->update([
                            'revoked' => true,
                            'revoked_at' => now(),
                            'revoked_reason' => $data['reason'],
                        ]);

                        Notification::make()
                            ->title('Clé révoquée')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLicenseKeys::route('/'),
            'create' => Pages\CreateLicenseKey::route('/create'),
            'edit' => Pages\EditLicenseKey::route('/{record}/edit'),
        ];
    }
}
