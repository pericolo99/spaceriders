<?php

namespace App\Livewire;

use App\Filament\Schemas\CampiCliente;
use App\Models\Cliente;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/** La scheda di un cliente nuovo; spesso è al banco, quindi si può passare dritti alla consegna. */
class NuovoClienteOperazione extends Operazione
{
    /** @var array<string, mixed> */
    public ?array $dati = [];

    #[On('apri-nuovo-cliente')]
    public function apri(): void
    {
        $this->mostra();
        $this->form->fill();
    }

    public function haDati(): bool
    {
        return collect($this->dati)->filter(fn ($valore) => filled($valore))->isNotEmpty();
    }

    protected function azzera(): void
    {
        $this->dati = [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('dati')
            ->model(Cliente::class)
            ->components(CampiCliente::completi());
    }

    public function salvaAction(): Action
    {
        return Action::make('salva')
            ->label('Salva')
            ->color('gray')
            ->size('lg')
            ->action(fn () => $this->salva());
    }

    public function salvaEConsegnaAction(): Action
    {
        return Action::make('salvaEConsegna')
            ->label('Salva e consegna')
            ->size('lg')
            ->action(fn () => $this->salva(consegna: true));
    }

    public function salva(bool $consegna = false): void
    {
        $cliente = Cliente::create($this->form->getState());

        Notification::make()->title('Cliente salvato')->body($cliente->nominativo)->success()->send();

        $this->completata();

        if ($consegna) {
            $this->dispatch('apri-consegna', cliente: $cliente->id);
        }
    }

    public function render(): View
    {
        return view('livewire.nuovo-cliente');
    }
}
