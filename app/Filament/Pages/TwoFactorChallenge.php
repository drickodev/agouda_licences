<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorChallenge extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.two-factor-challenge';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
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

    public function verify(): void
    {
        $user = Auth::user();
        $state = $this->form->getState();

        if (! app(Google2FA::class)->verifyKey($user->two_factor_secret, (string) $state['code'])) {
            Notification::make()->title('Code invalide')->danger()->send();

            return;
        }

        session(['two_factor_verified_user_id' => $user->id]);

        $this->redirect(Dashboard::getUrl());
    }
}
