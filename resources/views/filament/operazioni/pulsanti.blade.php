{{-- Consegna e Rientro sempre a portata, in alto accanto alla ricerca. --}}
<div class="flex items-center gap-2">
    <button type="button" x-data x-on:click="Livewire.dispatch('apri-consegna')" aria-label="Consegna"
        class="flex h-10 items-center gap-2 rounded-full bg-primary-600 px-3 text-sm font-semibold text-white hover:bg-primary-700 sm:px-4">
        <x-filament::icon icon="heroicon-m-arrow-right" class="h-5 w-5" />
        <span class="hidden sm:inline">Consegna</span>
    </button>
    <button type="button" x-data x-on:click="Livewire.dispatch('apri-rientro')" aria-label="Rientro"
        class="flex h-10 items-center gap-2 rounded-full bg-gray-100 px-3 text-sm font-semibold text-gray-950 hover:bg-gray-200 sm:px-4">
        <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
        <span class="hidden sm:inline">Rientro</span>
    </button>
</div>
