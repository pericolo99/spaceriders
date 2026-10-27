@props(['titolo', 'larga' => false, 'fissa' => false, 'annulla' => 'Annulla'])

{{--
    La finestra delle operazioni del banco.

    Non si chiude cliccando fuori né con Esc: solo dalla X o da «Annulla».
    Con `fissa` ha sempre la stessa altezza: su schermo largo il contenuto
    gestisce da sé lo scorrimento (lg:h-full sulle colonne), così errori ed
    elenchi lunghi non la fanno crescere né saltare. Con fissa="ovunque" lo fa
    anche sul telefono.
    Se l'operazione ha dati inseriti ($wire.haDati()), prima di uscire chiede
    conferma. Il contenuto c'è solo a finestra aperta ($aperto), così chiudendo
    si spegne anche la fotocamera dello scanner.
--}}
<div
    x-data="{
        conferma: false,
        async esci() {
            if (await $wire.haDati()) {
                this.conferma = true
            } else {
                $wire.chiudi()
            }
        },
    }"
    x-on:kk-esci.stop="esci()"
>
    @if ($this->aperto)
        <div class="fixed inset-0 z-[35] flex items-stretch justify-center bg-gray-950/40 sm:items-center sm:p-6"
            role="dialog" aria-modal="true" aria-labelledby="{{ $this->getId() }}-titolo">
            <div @class([
                'relative flex w-full flex-col bg-white sm:rounded-3xl sm:shadow-xl',
                'sm:max-h-[92vh]' => ! $fissa,
                'h-full sm:h-[88vh]' => $fissa,
                'sm:max-w-5xl' => $larga,
                'sm:max-w-2xl' => ! $larga,
            ])>
                <header class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 sm:px-7">
                    <h2 id="{{ $this->getId() }}-titolo" class="text-xl font-semibold tracking-tight text-gray-950">{{ $titolo }}</h2>
                    <button type="button" x-on:click="esci()" aria-label="Chiudi"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </header>

                <div @class([
                    'min-h-0 grow px-5 sm:px-7',
                    'overflow-y-auto py-6' => $fissa !== 'ovunque',
                    'lg:overflow-hidden lg:py-0' => $fissa === true,
                    'overflow-hidden' => $fissa === 'ovunque',
                ])>
                    {{ $slot }}
                </div>

                @isset($piede)
                    <footer class="flex flex-col-reverse gap-2 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-7">
                        <button type="button" x-on:click="esci()"
                            class="h-12 rounded-full px-6 text-base font-semibold text-gray-700 hover:bg-gray-100">
                            {{ $annulla }}
                        </button>
                        {{ $piede }}
                    </footer>
                @endisset

                {{-- La domanda prima di uscire, sopra la finestra. --}}
                <div x-show="conferma" x-cloak x-transition.opacity
                    class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 p-6 sm:rounded-3xl">
                    <div role="alertdialog" aria-labelledby="{{ $this->getId() }}-esci"
                        class="flex w-full max-w-sm flex-col gap-5 rounded-3xl border border-gray-200 bg-white p-6 shadow-lg">
                        <div class="flex flex-col gap-2">
                            <h3 id="{{ $this->getId() }}-esci" class="text-lg font-semibold text-gray-950">Vuoi uscire?</h3>
                            <p class="text-base text-gray-600">Quello che hai inserito andrà perso.</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <button type="button" x-on:click="conferma = false"
                                class="h-12 rounded-full bg-gray-950 text-base font-semibold text-white">
                                Resta
                            </button>
                            <button type="button" x-on:click="conferma = false; $wire.chiudi()"
                                class="h-12 rounded-full text-base font-semibold text-warning-700 hover:bg-warning-50">
                                Esci senza salvare
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
