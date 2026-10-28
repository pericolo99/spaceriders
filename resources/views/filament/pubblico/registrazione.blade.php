<x-filament-panels::page.simple>
    @if ($registrato)
        {{-- Il grazie: niente dati ripetuti a schermo, il telefono può essere di un altro. --}}
        <div class="flex flex-col items-center gap-5 py-4 text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-700">
                <x-filament::icon icon="heroicon-o-check" class="h-8 w-8" />
            </span>
            <div class="flex flex-col gap-2">
                <h2 class="text-2xl font-semibold tracking-tight text-gray-950">Grazie, {{ $registrato }}!</h2>
                <p class="text-base text-gray-600">La registrazione è completata. Al banco basta dire nome e cognome.</p>
            </div>
            <x-filament::button color="gray" wire:click="altraPersona" class="mt-2">
                Registra un'altra persona
            </x-filament::button>
        </div>
    @else
        {{ $this->content }}
    @endif
</x-filament-panels::page.simple>
