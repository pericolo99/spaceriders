@php
    use App\Enums\Esito;
    use App\Filament\Resources\Noleggi\NoleggioResource;
    use App\Livewire\RientroOperazione;

    $noleggio = $this->getNoleggio();
    $aperte = $this->righeAperte();
    $rientrate = $this->righeRientrate();
    $scelte = RientroOperazione::scelte();
@endphp

<div>
    <x-kk.finestra :titolo="$noleggio ? 'Rientro · '.$noleggio->cliente->nominativo : 'Rientro'" :annulla="$noleggio ? 'Annulla' : 'Chiudi'" larga fissa="ovunque">
        @unless ($noleggio)
            {{-- Prima della lettura: si cerca il pezzo, e con lui si apre il suo noleggio. --}}
            <div class="mx-auto flex h-full w-full max-w-2xl flex-col gap-4 overflow-y-auto py-6">
                @include('filament.pages.partials.scanner', ['metodo' => 'leggi', 'segnaposto' => 'Leggi o cerca un pezzo che torna'])
                <p class="text-center text-gray-500">Compare tutto il noleggio di quel pezzo, da smarcare pezzo per pezzo.</p>
            </div>
        @endunless

        @if ($noleggio)
            {{--
                La finestra ha misura fissa, anche sul telefono: noleggio, lettore e
                titolo restano fermi in alto, scorre solo l'elenco dei pezzi.
            --}}
            <div class="flex h-full flex-col">
                <div class="flex shrink-0 flex-col gap-4 pt-4 sm:gap-6 sm:pt-6">

                    <section aria-label="Noleggio" class="flex flex-wrap gap-x-6 gap-y-2 text-base sm:gap-x-10 sm:gap-y-3">
                        <div class="flex flex-col">
                            {{-- Sul telefono il nome è già nel titolo: si risparmia spazio per l'elenco. --}}
                            <span class="text-sm text-gray-500 max-sm:hidden">Cliente</span>
                            <span class="font-semibold text-gray-950 max-sm:hidden">{{ $noleggio->cliente->nominativo }}</span>
                            @if ($noleggio->cliente->telefono)
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $noleggio->cliente->telefono) }}" class="text-sm font-medium text-primary-700">
                                    {{ $noleggio->cliente->telefono }}
                                </a>
                            @endif
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm text-gray-500">Consegnato</span>
                            <span class="text-gray-950">{{ $noleggio->data_consegna->format('d/m/Y') }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm text-gray-500">Rientro previsto</span>
                            <span @class(['font-semibold text-warning-700' => $noleggio->isInRitardo(), 'text-gray-950' => ! $noleggio->isInRitardo()])>
                                {{ $noleggio->data_rientro->format('d/m/Y') }}
                                @if ($noleggio->isInRitardo())
                                    · {{ $noleggio->giorniDiRitardo() }} {{ $noleggio->giorniDiRitardo() === 1 ? 'giorno' : 'giorni' }} di ritardo
                                @endif
                            </span>
                        </div>
                        @if ($noleggio->sacche())
                            <div class="flex flex-col">
                                <span class="text-sm text-gray-500">Sacche da ritirare</span>
                                <span class="font-semibold text-gray-950">{{ $noleggio->sacche() }}</span>
                            </div>
                        @endif
                        <div class="flex gap-4 text-sm font-medium sm:ml-auto sm:self-start">
                            @if ($esiti === [])
                                <button type="button" wire:click="cambiaNoleggio" class="text-gray-600 hover:text-primary-700">Altro noleggio</button>
                            @endif
                            <a href="{{ NoleggioResource::getUrl('view', ['record' => $noleggio]) }}" class="text-gray-600 hover:text-primary-700">Apri la scheda</a>
                        </div>
                    </section>

                    @if ($noleggio->da_pagare)
                        {{-- Il promemoria della consegna: si spunta quando il cliente paga. --}}
                        <label @class([
                            'flex cursor-pointer items-center gap-3 rounded-2xl px-4 py-3 ring-1',
                            'bg-danger-50 ring-danger-200' => ! $pagato,
                            'bg-primary-50 ring-primary-200' => $pagato,
                        ])>
                            <input type="checkbox" wire:model.live="pagato"
                                class="h-6 w-6 shrink-0 rounded-md border-gray-300 text-primary-600 focus:ring-primary-600">
                            <span class="flex min-w-0 flex-col">
                                <span @class(['text-base font-semibold', 'text-danger-700' => ! $pagato, 'text-primary-800' => $pagato])>
                                    {{ $pagato ? 'Pagato adesso' : 'Da pagare' }}
                                </span>
                                @if ($noleggio->note_pagamento)
                                    <span class="text-sm font-medium text-gray-900">{{ $noleggio->note_pagamento }}</span>
                                @endif
                                @unless ($pagato)
                                    <span class="text-sm text-gray-600">Spunta quando il cliente paga.</span>
                                @endunless
                            </span>
                        </label>
                    @endif

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="grow">
                            @include('filament.pages.partials.scanner', ['metodo' => 'leggi', 'senzaSuggerimenti' => true, 'segnaposto' => 'Leggi il codice di un pezzo'])
                        </div>
                        @if ($aperte->isNotEmpty())
                            <button type="button" wire:click="tuttiAPosto"
                                class="h-14 shrink-0 max-sm:hidden rounded-full bg-primary-50 px-6 text-base font-semibold text-primary-800 hover:bg-primary-100">
                                Tutti a posto
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-3 pb-2">
                        <h3 id="{{ $this->getId() }}-fuori" class="text-lg font-semibold text-gray-950">
                            Ancora fuori · {{ $aperte->count() }}
                            @if ($esiti)
                                <span class="font-normal text-gray-500">· smarcati {{ count($esiti) }}</span>
                            @endif
                        </h3>
                        {{-- Sul telefono sta qui: accanto al lettore non c'è posto. --}}
                        @if ($aperte->isNotEmpty())
                            <button type="button" wire:click="tuttiAPosto"
                                class="h-10 shrink-0 rounded-full bg-primary-50 px-4 text-sm font-semibold text-primary-800 hover:bg-primary-100 sm:hidden">
                                Tutti a posto
                            </button>
                        @endif
                    </div>
                </div>

                <div class="-mx-2 flex min-h-0 grow flex-col gap-6 overflow-y-auto px-2 pb-6 lg:pr-4">
                    <section aria-labelledby="{{ $this->getId() }}-fuori" class="flex flex-col">

                        @forelse ($aperte as $riga)
                            @php($scelto = $esiti[$riga->id] ?? null)
                            <div wire:key="riga-{{ $riga->id }}" @class([
                                'flex flex-col gap-3 border-t border-gray-100 px-2 py-4 transition',
                                'bg-primary-50/60' => $scelto === Esito::Integro->value,
                                'bg-warning-50/60' => $scelto && $scelto !== Esito::Integro->value,
                            ])>
                                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                                    <div class="flex min-w-0 grow items-center gap-4">
                                        <span class="relative shrink-0">
                                            <img src="{{ $riga->attrezzatura->immagine() }}" alt="" class="h-12 w-12 rounded-xl bg-white object-contain p-1.5">
                                            @if ($scelto)
                                                <span @class([
                                                    'absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full text-white ring-2 ring-white',
                                                    'bg-primary-600' => $scelto === Esito::Integro->value,
                                                    'bg-warning-600' => $scelto !== Esito::Integro->value,
                                                ])>
                                                    <x-filament::icon :icon="$scelto === Esito::Integro->value ? 'heroicon-m-check' : 'heroicon-m-exclamation-triangle'" class="h-3 w-3" />
                                                </span>
                                            @endif
                                        </span>
                                        <span class="flex min-w-0 flex-col">
                                            <span class="truncate text-base font-semibold text-gray-950">{{ $riga->attrezzatura->nome }}</span>
                                            <span class="truncate text-sm text-gray-500">{{ $riga->attrezzatura->tipologia->getLabel() }} · {{ $riga->attrezzatura->codice }}</span>
                                        </span>
                                    </div>

                                    <div role="radiogroup" aria-label="Com'è tornato {{ $riga->attrezzatura->nome }}" class="grid shrink-0 grid-cols-2 gap-1.5 sm:grid-cols-4">
                                        @foreach ($scelte as $valore => $etichetta)
                                            @php($attivo = $scelto === $valore)
                                            <button type="button" role="radio" aria-checked="{{ $attivo ? 'true' : 'false' }}"
                                                wire:click="scegli({{ $riga->id }}, '{{ $valore }}')"
                                                @class([
                                                    'h-11 rounded-full border px-3 text-sm font-semibold transition',
                                                    'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => ! $attivo,
                                                    'border-primary-600 bg-primary-600 text-white' => $attivo && $valore === Esito::Integro->value,
                                                    'border-gray-700 bg-gray-700 text-white' => $attivo && $valore === Esito::DaControllare->value,
                                                    'border-warning-600 bg-warning-600 text-white' => $attivo && ! in_array($valore, [Esito::Integro->value, Esito::DaControllare->value], true),
                                                ])>{{ $etichetta }}</button>
                                        @endforeach
                                    </div>
                                </div>

                                @if ($scelto && $scelto !== Esito::Integro->value)
                                    <div class="flex flex-col gap-3 md:pl-16">
                                        <label class="flex flex-col gap-1.5">
                                            <span class="text-sm font-medium text-gray-700">Che cosa hai notato</span>
                                            <input type="text" wire:model.blur="note.{{ $riga->id }}"
                                                class="h-11 rounded-xl border border-gray-200 px-4 text-base focus:border-primary-600 focus:ring-primary-600"
                                                placeholder="{{ $scelto === Esito::NonRiconsegnato->value ? 'Es. il cliente dice di averlo perso in pista' : 'Es. soletta rigata, attacco allentato' }}">
                                        </label>

                                        @if (RientroOperazione::conFoto($scelto))
                                            @php($fotoRiga = $foto[$riga->id] ?? [])
                                            <div class="flex flex-col gap-1.5">
                                                <span class="text-sm font-medium text-gray-700">Foto</span>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($fotoRiga as $i => $immagine)
                                                        <div wire:key="foto-{{ $riga->id }}-{{ $i }}" class="relative h-20 w-20">
                                                            <img src="{{ $immagine->temporaryUrl() }}" alt="Foto {{ $i + 1 }} di {{ $riga->attrezzatura->nome }}"
                                                                class="h-20 w-20 rounded-xl object-cover">
                                                            <button type="button" wire:click="togliFoto({{ $riga->id }}, {{ $i }})" aria-label="Togli la foto {{ $i + 1 }}"
                                                                class="absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-gray-950 text-white ring-2 ring-white">
                                                                <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    @endforeach

                                                    @if (count($fotoRiga) < RientroOperazione::FOTO_PER_PEZZO)
                                                        <label class="flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-[1.5px] border-dashed border-gray-300 text-xs font-semibold text-gray-700 hover:border-primary-600 hover:text-primary-700">
                                                            <span wire:loading.remove wire:target="nuove.{{ $riga->id }}" class="flex flex-col items-center gap-1">
                                                                <x-filament::icon icon="heroicon-o-camera" class="h-6 w-6" />
                                                                Aggiungi
                                                            </span>
                                                            <span wire:loading wire:target="nuove.{{ $riga->id }}">Carico…</span>
                                                            <input type="file" accept="image/*" multiple class="sr-only"
                                                                wire:model="nuove.{{ $riga->id }}">
                                                        </label>
                                                    @endif
                                                </div>
                                                @error('nuove.'.$riga->id.'.*')
                                                    <span class="text-sm text-danger-600">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="border-t border-gray-100 py-6 text-center text-gray-500">Tutti i pezzi sono già rientrati.</p>
                        @endforelse
                    </section>

                    @if ($rientrate->isNotEmpty())
                        <section aria-labelledby="{{ $this->getId() }}-rientrati" class="flex flex-col">
                            <h3 id="{{ $this->getId() }}-rientrati" class="pb-2 text-base font-semibold text-gray-500">Già rientrati · {{ $rientrate->count() }}</h3>
                            @foreach ($rientrate as $riga)
                                <div wire:key="rientrata-{{ $riga->id }}" class="flex items-center gap-4 border-t border-gray-100 py-3 opacity-70">
                                    <img src="{{ $riga->attrezzatura->immagine() }}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-contain p-1 {{ $riga->attrezzatura->sfondoImmagine() }}">
                                    <span class="min-w-0 grow truncate text-gray-700">{{ $riga->attrezzatura->nome }}</span>
                                    <span class="shrink-0 text-sm text-gray-500">{{ $riga->rientrata_at->format('d/m') }}</span>
                                    <x-filament::badge :color="$riga->esito?->getColor() ?? 'gray'">{{ $riga->esito?->getLabel() ?? '—' }}</x-filament::badge>
                                </div>
                            @endforeach
                        </section>
                    @endif
                </div>
            </div>
        @endif

        <x-slot:piede>
            @if ($noleggio)
                {{ $this->registraAction }}
            @endif
        </x-slot:piede>
    </x-kk.finestra>

    <x-filament-actions::modals />
</div>
