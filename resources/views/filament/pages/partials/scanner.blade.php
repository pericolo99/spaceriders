{{--
    Lettura del codice: fotocamera del telefono, lettore laser o tastiera.
    $metodo è il metodo Livewire che riceve il codice scelto.

    Mentre si digita compaiono i suggerimenti ($this->getSuggerimenti()):
    si sceglie col dito o con frecce e Invio. L'elenco sta nel flusso, non
    sospeso: dentro una finestra che scorre verrebbe tagliato. Il lettore laser scrive tutto il
    codice e preme Invio senza scegliere nulla, e il codice passa così com'è.
--}}
@php
    // $senzaSuggerimenti: dove i pezzi sono già tutti in vista (rientro di un noleggio).
    $suggerimenti = ($senzaSuggerimenti ?? false) ? [] : $this->getSuggerimenti();
    // $sospesi: su schermo largo l'elenco si apre sopra il contenuto che segue
    // invece di spingerlo giù (la Consegna ha colonne a misura fissa).
    $sospesi ??= false;
    $segnaposto ??= 'Codice, marca o modello';
@endphp

<div x-data="scanner($wire, '{{ $metodo }}')" class="flex flex-col gap-3">
    <div x-show="attivo" x-cloak class="kk-camera">
        <video x-ref="video" playsinline muted></video>
        <div class="kk-camera-mirino"></div>
        <button type="button" x-on:click="ferma()"
            class="absolute right-3 top-3 rounded-full bg-black/50 px-4 py-2 text-sm font-semibold text-white">
            Chiudi
        </button>
    </div>

    <div
        x-data="{
            scelto: -1,
            nascosti: false,
            voci() { return this.$refs.elenco ? [...this.$refs.elenco.querySelectorAll('[data-scegli]')] : [] },
            muovi(passo) {
                const n = this.voci().length
                if (n === 0) return
                this.nascosti = false
                this.scelto = (this.scelto + passo + n) % n
                this.voci()[this.scelto]?.scrollIntoView({ block: 'nearest' })
            },
            invio(evento) {
                if (this.scelto >= 0 && ! this.nascosti && this.voci()[this.scelto]) {
                    evento.preventDefault()
                    this.voci()[this.scelto].click()
                }
            },
        }"
        @class(['lg:relative' => $sospesi])
    >
        <form wire:submit="{{ $metodo }}" class="flex gap-2">
            <label class="sr-only" for="codice-{{ $metodo }}">Codice, marca o modello</label>
            <input id="codice-{{ $metodo }}" type="text" autocomplete="off" autofocus
                wire:model.live.debounce.250ms="codice"
                x-on:keydown.arrow-down.prevent="muovi(1)"
                x-on:keydown.arrow-up.prevent="muovi(-1)"
                x-on:keydown.enter="invio($event)"
                x-on:keydown.escape.prevent.stop="nascosti = true"
                x-on:input="nascosti = false; scelto = -1"
                role="combobox" aria-autocomplete="list" aria-controls="suggerimenti-{{ $metodo }}"
                aria-expanded="{{ $suggerimenti ? 'true' : 'false' }}"
                placeholder="{{ $segnaposto }}"
                class="h-14 min-w-0 grow rounded-full border-0 bg-gray-100 px-5 text-lg text-gray-950 placeholder:text-gray-500 focus:ring-2 focus:ring-primary-600" />
            <button type="button" x-show="! attivo" x-on:click="avvia()" aria-label="Usa la fotocamera"
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gray-950 text-white">
                <x-filament::icon icon="heroicon-o-camera" class="h-6 w-6" />
            </button>
        </form>

        @if ($suggerimenti)
            <ul id="suggerimenti-{{ $metodo }}" role="listbox" x-ref="elenco" x-show="! nascosti"
                x-init="$nextTick(() => $el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }))"
                @class([
                    'mt-2 max-h-[26rem] overflow-y-auto rounded-3xl border border-gray-200 bg-white p-2',
                    'lg:absolute lg:inset-x-0 lg:top-full lg:z-20 lg:max-h-[50vh] lg:shadow-xl' => $sospesi,
                ])>
                @foreach ($suggerimenti as $s)
                    <li wire:key="sugg-{{ $metodo }}-{{ $s['codice'] }}" role="option">
                        @if ($s['scegli'])
                            <button type="button" data-scegli
                                wire:click="{{ $metodo }}('{{ e($s['codice']) }}')"
                                x-bind:class="voci().indexOf($el) === scelto ? 'bg-primary-50' : ''"
                                x-on:mouseenter="scelto = voci().indexOf($el)"
                                class="flex w-full items-center gap-4 rounded-2xl p-2 text-left hover:bg-primary-50">
                        @else
                            <div class="flex w-full items-center gap-4 rounded-2xl p-2 opacity-60" aria-disabled="true">
                        @endif
                                <img src="{{ $s['immagine'] }}" alt="" loading="lazy"
                                    class="h-14 w-14 shrink-0 rounded-xl object-contain p-2 {{ $s['sfondo'] }}">
                                <span class="flex min-w-0 grow flex-col gap-0.5">
                                    <span class="truncate text-base font-semibold text-gray-950">{{ $s['nome'] }}</span>
                                    <span class="truncate text-sm text-gray-500">{{ $s['dettaglio'] }}</span>
                                    <span @class([
                                        'truncate text-sm font-medium',
                                        'text-primary-700' => $s['colore'] === 'primary',
                                        'text-warning-700' => $s['colore'] === 'warning',
                                        'text-gray-600' => $s['colore'] === 'gray',
                                    ])>{{ $s['stato'] }}</span>
                                </span>
                        @if ($s['scegli'])
                                <x-filament::icon icon="heroicon-m-plus-circle" class="h-6 w-6 shrink-0 text-primary-600" />
                            </button>
                        @else
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p x-show="errore" x-text="errore" x-cloak class="text-sm text-warning-700"></p>
</div>
