<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Sécurité (2FA)';

    protected static string $view = 'filament.pages.two-factor-settings';

    public ?array $data = [];

    public ?string $qrCodeInline = null;

    public ?string $pendingSecret = null;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user->hasTwoFactorEnabled()) {
            $this->pendingSecret = $user->two_factor_secret ?? app(Google2FA::class)->generateSecretKey();

            if ($user->two_factor_secret === null) {
                $user->forceFill(['two_factor_secret' => $this->pendingSecret])->save();
            }

            $this->qrCodeInline = app(Google2FA::class)->getQRCodeInline(
                config('app.name'),
                $user->email,
                $this->pendingSecret,
            );
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Code à 6 chiffres')
                    ->required()
                    ->numeric()
                    ->autocomplete('one-time-code'),
            ])
            ->statePath('data');
    }

    public function isTwoFactorEnabled(): bool
    {
        return Auth::user()->hasTwoFactorEnabled();
    }

    public function enable(): void
    {
        $user = Auth::user();
        $state = $this->form->getState();

        if (! app(Google2FA::class)->verifyKey($user->two_factor_secret, (string) $state['code'])) {
            Notification::make()->title('Code invalide')->danger()->send();

            return;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        session(['two_factor_verified_user_id' => $user->id]);

        Notification::make()->title('2FA activé')->success()->send();

        $this->redirect(static::getUrl());
    }

    public function disable(): void
    {
        $user = Auth::user();
        $state = $this->form->getState();

        if (! app(Google2FA::class)->verifyKey($user->two_factor_secret, (string) $state['code'])) {
            Notification::make()->title('Code invalide')->danger()->send();

            return;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        session()->forget('two_factor_verified_user_id');

        Notification::make()->title('2FA désactivé')->success()->send();

        $this->redirect(static::getUrl());
    }
}
