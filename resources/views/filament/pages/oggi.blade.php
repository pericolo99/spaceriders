@php
    use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
    use App\Filament\Resources\Noleggi\NoleggioResource;

    $percento = fn (int $parte, int $tutto) => $tutto > 0 ? min(100, round($parte / $tutto * 100)) : 0;
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-10">

        {{-- I numeri della giornata: una fascia sola, senza riquadri. --}}
        <section aria-label="Numeri di oggi"
            class="grid grid-cols-2 gap-x-8 gap-y-6 border-y border-gray-100 py-6 lg:grid-cols-4">
            <div class="flex flex-col gap-1.5">
                <span class="text-sm text-gray-600">Consegne di oggi</span>
                <span class="text-4xl font-semibold tracking-tight text-gray-950">{{ $consegne }}</span>
                <a href="{{ NoleggioResource::getUrl('index', ['filters' => ['periodo' => ['value' => 'consegnati_oggi']]]) }}"
                    class="text-sm text-gray-600 hover:text-primary-700">Vedi i noleggi</a>
            </div>

            <div class="flex flex-col gap-1.5">
                <span class="text-sm text-gray-600">Rientri di oggi</span>
                <span class="text-4xl font-semibold tracking-tight text-gray-950">
                    {{ $rientri[0] }}<span class="text-xl font-medium text-gray-400"> / {{ $rientri[1] }}</span>
                </span>
                <div class="mt-1.5 h-1 rounded-full bg-gray-100">
                    <div class="h-1 rounded-full bg-primary-600" style="width: {{ $percento($rientri[0], $rientri[1]) }}%"></div>
                </div>
            </div>

            <a href="{{ NoleggioResource::getUrl('index', ['filters' => ['stato' => ['value' => 'ritardo']]]) }}"
                class="flex flex-col gap-1.5">
                <span class="text-sm text-gray-600">In ritardo</span>
                <span @class(['text-4xl font-semibold tracking-tight', 'text-warning-700' => $inRitardo, 'text-gray-950' => ! $inRitardo])>{{ $inRitardo }}</span>
                @if ($inRitardo)
                    <span class="text-sm font-semibold text-warning-700">Vedi chi sollecitare</span>
                @endif
            </a>

            <div class="flex flex-col gap-1.5">
                <span class="text-sm text-gray-600">Attrezzatura libera</span>
                <span class="text-4xl font-semibold tracking-tight text-gray-950">
                    {{ $libera[0] }}<span class="text-xl font-medium text-gray-400"> / {{ $libera[1] }}</span>
                </span>
                @if ($daControllare)
                    <a href="{{ AttrezzaturaResource::getUrl('index', ['tab' => 'da_controllare']) }}"
                        class="text-sm text-gray-600 hover:text-primary-700">{{ $daControllare }} da controllare</a>
                @endif
            </div>
        </section>

        {{-- Le azioni del banco. --}}
        <section aria-label="Azioni" class="grid gap-4 sm:grid-cols-3">
            <button type="button" wire:click="$dispatch('apri-consegna')"
                class="text-left flex flex-col gap-7 rounded-3xl bg-primary-50 p-6 text-gray-950 transition hover:bg-primary-100">
                <x-filament::icon icon="heroicon-o-arrow-right" class="h-7 w-7 text-primary-700" />
                <span class="flex flex-col gap-1">
                    <span class="text-lg font-semibold">Consegna</span>
                    <span class="text-sm text-gray-600">Scansiona e consegna il materiale</span>
                </span>
            </button>
            <button type="button" wire:click="$dispatch('apri-rientro')"
                class="text-left flex flex-col gap-7 rounded-3xl bg-gray-100 p-6 text-gray-950 transition hover:bg-gray-200/70">
                <x-filament::icon icon="heroicon-o-arrow-left" class="h-7 w-7" />
                <span class="flex flex-col gap-1">
                    <span class="text-lg font-semibold">Rientro</span>
                    <span class="text-sm text-gray-600">Controlla e registra cosa torna</span>
                </span>
            </button>
            <button type="button" wire:click="$dispatch('apri-nuovo-cliente')"
                class="text-left flex flex-col gap-7 rounded-3xl bg-gray-100 p-6 text-gray-950 transition hover:bg-gray-200/70">
                <x-filament::icon icon="heroicon-o-user-plus" class="h-7 w-7" />
                <span class="flex flex-col gap-1">
                    <span class="text-lg font-semibold">Nuovo cliente</span>
                    <span class="text-sm text-gray-600">Dati e contatti per il noleggio</span>
                </span>
            </button>
        </section>

        {{-- Che cosa deve tornare. --}}
        <section aria-labelledby="da-fare" class="flex flex-col">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3">
                <h2 id="da-fare" class="text-xl font-semibold tracking-tight text-gray-950">Da far rientrare</h2>
                <div role="tablist" aria-label="Filtro" class="flex gap-1">
                    @foreach (['tutto' => 'Tutto', 'oggi' => 'Oggi', 'ritardo' => 'In ritardo'] as $chiave => $etichetta)
                        <button type="button" role="tab" wire:click="$set('filtro', '{{ $chiave }}')"
                            aria-selected="{{ $filtro === $chiave ? 'true' : 'false' }}"
                            @class([
                                'h-10 rounded-full px-4 text-sm font-medium transition',
                                'bg-gray-950 text-white' => $filtro === $chiave,
                                'text-gray-600 hover:bg-gray-100' => $filtro !== $chiave,
                            ])>{{ $etichetta }}</button>
                    @endforeach
                </div>
            </div>

            @forelse ($daFare as $noleggio)
                @php
                    $quando = $noleggio->data_rientro->isToday()
                        ? 'oggi'
                        : $noleggio->data_rientro->translatedFormat($noleggio->data_rientro->isCurrentYear() ? 'j M' : 'j M Y');
                    $ritardo = $noleggio->giorniDiRitardo();
                @endphp
                <button type="button" wire:click="$dispatch('apri-rientro-noleggio', { noleggio: {{ $noleggio->id }} })"
                    class="flex w-full items-center gap-4 border-t border-gray-100 px-2 py-4 text-left hover:bg-gray-50 sm:gap-5">
                    {{-- Su schermo largo la data ha la sua colonna; sul telefono scende sotto il nome. --}}
                    <span class="hidden w-24 shrink-0 text-sm text-gray-500 sm:block">{{ $quando }}</span>
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="truncate text-base font-semibold text-gray-950">{{ $noleggio->cliente->nominativo }}</span>
                        <span class="truncate text-sm text-gray-500">
                            {{ $noleggio->righe_aperte_count }} {{ $noleggio->righe_aperte_count === 1 ? 'pezzo' : 'pezzi' }} fuori
                            @if ($noleggio->sacche())
                                · sacche: {{ $noleggio->sacche() }}
                            @endif
                        </span>
                        <span @class(['text-sm font-medium sm:hidden', 'text-warning-700' => $ritardo, 'text-gray-600' => ! $ritardo])>
                            {{ $ritardo ? "Dal {$quando} · {$ritardo} ".($ritardo === 1 ? 'giorno' : 'giorni').' di ritardo' : 'Rientro oggi' }}
                        </span>
                    </span>
                    @if ($ritardo)
                        <span class="hidden shrink-0 items-center gap-2 text-sm font-medium text-warning-700 sm:flex">
                            <span class="h-2 w-2 rounded-full bg-warning-600"></span>
                            {{ $ritardo }} {{ $ritardo === 1 ? 'giorno' : 'giorni' }} di ritardo
                        </span>
                    @else
                        <span class="hidden shrink-0 items-center gap-2 text-sm font-medium text-gray-600 sm:flex">
                            <span class="h-2 w-2 rounded-full bg-primary-600"></span>
                            Rientro
                        </span>
                    @endif
                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0 text-gray-400" />
                </button>
            @empty
                <p class="border-t border-gray-100 px-2 py-8 text-center text-gray-500">
                    Niente da far rientrare{{ $filtro === 'ritardo' ? ': nessun noleggio in ritardo' : ' oggi' }}.
                </p>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
