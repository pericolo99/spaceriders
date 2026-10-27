<div>
    <x-kk.finestra titolo="Consegna" larga fissa="ovunque">
        {{--
            La finestra non cambia mai misura. Su schermo largo le due colonne
            scorrono ognuna per conto suo; sul telefono diventano due schede.
        --}}
        <div x-data="{ scheda: @js($clienteIniziale ? 'materiale' : 'dati') }"
            class="flex h-full flex-col lg:grid lg:grid-cols-2 lg:gap-8">
            <div role="tablist" class="grid shrink-0 grid-cols-2 gap-1 rounded-full bg-gray-100 p-1 mt-4 lg:hidden">
                <button type="button" role="tab" x-on:click="scheda = 'dati'" :aria-selected="scheda === 'dati'"
                    :class="scheda === 'dati' ? 'bg-white text-gray-950 shadow-sm' : 'text-gray-600'"
                    class="relative h-11 rounded-full text-base font-semibold">
                    Cliente e date
                    @if ($errors->isNotEmpty())
                        <span class="absolute right-3 top-2.5 h-2 w-2 rounded-full bg-danger-600" aria-label="Da correggere"></span>
                        {{-- Se la conferma trova errori nei dati, si torna alla loro scheda. --}}
                        <span x-init="scheda = 'dati'" hidden></span>
                    @endif
                </button>
                <button type="button" role="tab" x-on:click="scheda = 'materiale'" :aria-selected="scheda === 'materiale'"
                    :class="scheda === 'materiale' ? 'bg-white text-gray-950 shadow-sm' : 'text-gray-600'"
                    class="h-11 rounded-full text-base font-semibold">
                    Materiale · {{ count($pezzi) }}
                </button>
            </div>

            <div :class="scheda !== 'dati' && 'max-lg:hidden'"
                class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto py-6 lg:pr-3">
                {{ $this->form }}
            </div>

            <section aria-labelledby="{{ $this->getId() }}-materiale" :class="scheda !== 'materiale' && 'max-lg:hidden'"
                class="flex min-h-0 flex-1 flex-col gap-4 py-6">
                <div class="flex items-baseline justify-between">
                    <h3 id="{{ $this->getId() }}-materiale" class="text-lg font-semibold text-gray-950">Materiale</h3>
                    <span class="text-sm text-gray-600">
                        <strong class="font-semibold text-gray-950">{{ count($pezzi) }}</strong>
                        {{ count($pezzi) === 1 ? 'pezzo' : 'pezzi' }}
                    </span>
                </div>

                @include('filament.pages.partials.scanner', ['metodo' => 'aggiungi', 'sospesi' => true])

                <ul class="flex min-h-0 flex-1 flex-col overflow-y-auto pr-1">
                    @forelse ($this->getPezziLetti() as $pezzo)
                        <li wire:key="pezzo-{{ $pezzo->id }}" class="flex items-center gap-4 border-b border-gray-100 py-3">
                            <span class="relative shrink-0">
                                <img src="{{ $pezzo->immagine() }}" alt="" class="h-12 w-12 rounded-xl object-contain p-1.5 {{ $pezzo->sfondoImmagine() }}">
                                <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-white ring-2 ring-white">
                                    <x-filament::icon icon="heroicon-m-check" class="h-3 w-3" />
                                </span>
                            </span>
                            <span class="flex min-w-0 grow flex-col">
                                <span class="truncate text-base font-medium text-gray-950">{{ $pezzo->nome }}</span>
                                <span class="text-sm text-gray-500">{{ $pezzo->tipologia->getLabel() }} · {{ $pezzo->codice }}</span>
                            </span>
                            <button type="button" wire:click="rimuovi({{ $pezzo->id }})" aria-label="Togli {{ $pezzo->nome }}"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                                <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                            </button>
                        </li>
                    @empty
                        <li class="py-6 text-center text-gray-500">Nessun pezzo letto.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <x-slot:piede>
            {{ $this->confermaAction }}
        </x-slot:piede>
    </x-kk.finestra>

    <x-filament-actions::modals />
</div>
