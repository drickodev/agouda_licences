<x-filament-panels::page>
    <form wire:submit="verify" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Vérifier
        </x-filament::button>
    </form>
</x-filament-panels::page>
