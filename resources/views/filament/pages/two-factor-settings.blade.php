<x-filament-panels::page>
    @if ($this->isTwoFactorEnabled())
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                L'authentification à deux facteurs est <strong>activée</strong> sur ce compte.
                Saisis un code actuel pour la désactiver.
            </p>
        </div>

        <form wire:submit="disable" class="mt-6 space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" color="danger">
                Désactiver le 2FA
            </x-filament::button>
        </form>
    @else
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Scanne ce QR code avec une application d'authentification (Google Authenticator,
                Authy, 1Password…), puis saisis le code généré pour confirmer l'activation.
            </p>

            <div class="mt-4 flex justify-center">
                <img src="{{ $qrCodeInline }}" alt="QR code 2FA" width="200" height="200">
            </div>

            <p class="mt-4 text-center font-mono text-sm text-gray-500 dark:text-gray-400">
                Saisie manuelle : {{ $pendingSecret }}
            </p>
        </div>

        <form wire:submit="enable" class="mt-6 space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit">
                Activer le 2FA
            </x-filament::button>
        </form>
    @endif
</x-filament-panels::page>
