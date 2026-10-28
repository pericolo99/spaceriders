<x-filament-panels::page.simple>
    {{--
        Come nel vecchio modulo: la foto di neve fresca dietro, il modulo bianco
        sopra. Del marchio resta solo il logo: la pagina è dei clienti, il nome
        dell'app non dice loro niente.
    --}}
    <style>
        .kk-registrazione .fi-simple-layout {
            background: #dfe7ef url('{{ asset('img/neve.jpg') }}') center 65% / cover no-repeat;
        }
        .kk-registrazione .fi-simple-main {
            box-shadow: 0 20px 50px -20px rgb(15 23 42 / 0.45);
        }
        .kk-registrazione .kk-brand-testo {
            display: none;
        }
        @media (max-width: 639px) {
            .kk-registrazione .fi-simple-main-ctn {
                padding: 1rem 0.75rem 0;
            }
            .kk-registrazione .fi-simple-main {
                border-radius: 1.5rem;
                padding-inline: 1.25rem;
            }
        }
    </style>

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
