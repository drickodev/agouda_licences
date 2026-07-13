<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivationResource\Pages;
use App\Models\AccountRecoveryCode;
use App\Models\Activation;
use App\Services\AccountRecoveryCodeGenerator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ActivationResource extends Resource
{
    protected static ?string $model = Activation::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'Activations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('license_key_id')
                    ->relationship('licenseKey', 'key_last4')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('machine_fingerprint')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('machine_name')
                    ->maxLength(120),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'released' => 'Libérée',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('last_ip')
                    ->label('Dernière IP'),
                Forms\Components\DateTimePicker::make('last_seen_at')
                    ->label('Dernier phone-home'),
                Forms\Components\DateTimePicker::make('released_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('licenseKey.key_last4')
                    ->label('Clé')
                    ->formatStateUsing(fn (?string $state) => $state ? "••••-••••-••••-{$state}" : null)
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('machine_name')
                    ->label('Machine')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('machine_fingerprint')
                    ->label('Empreinte')
                    ->limit(24)
                    ->tooltip(fn (Activation $record) => $record->machine_fingerprint),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Active' : 'Libérée')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('last_ip')
                    ->label('Dernière IP')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Dernier phone-home')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'released' => 'Libérée',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('release')
                    ->label('Libérer')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Activation $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function (Activation $record) {
                        $record->update([
                            'status' => 'released',
                            'released_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Siège libéré')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('generateAccountRecoveryCode')
                    ->label('Débloquer le compte')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->modalHeading('Générer un code de déblocage')
                    ->modalDescription('À usage unique, valable '.config('license.account_recovery.ttl_minutes').' minutes, utilisable uniquement sur cette machine (empreinte vérifiée). Communique-le au client par téléphone/WhatsApp, jamais par un canal non vérifié.')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Motif (facultatif)')
                            ->placeholder('Ex : client a oublié son mot de passe après une réinstallation Windows'),
                    ])
                    ->action(function (Activation $record, array $data) {
                        $plaintext = app(AccountRecoveryCodeGenerator::class)->generateUniqueCode();

                        AccountRecoveryCode::query()->create([
                            'activation_id' => $record->id,
                            'code_hash' => AccountRecoveryCodeGenerator::hash($plaintext),
                            'created_by' => Auth::id(),
                            'expires_at' => now()->addMinutes((int) config('license.account_recovery.ttl_minutes')),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Code généré — communique-le maintenant')
                            ->body("Ce code ne sera plus jamais affiché : {$plaintext}\n\nValable ".config('license.account_recovery.ttl_minutes').' minutes, usage unique, sur cette machine uniquement.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListActivations::route('/'),
            'create' => Pages\CreateActivation::route('/create'),
            'edit' => Pages\EditActivation::route('/{record}/edit'),
        ];
    }
}
