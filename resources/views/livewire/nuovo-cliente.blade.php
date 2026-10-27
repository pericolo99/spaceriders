<div>
    <x-kk.finestra titolo="Nuovo cliente">
        {{ $this->form }}

        <x-slot:piede>
            {{ $this->salvaAction }}
            {{ $this->salvaEConsegnaAction }}
        </x-slot:piede>
    </x-kk.finestra>

    <x-filament-actions::modals />
</div>
