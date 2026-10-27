<?php

namespace App\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

/**
 * Un'operazione del banco (consegna, rientro, nuovo cliente) in una finestra
 * che si apre da qualunque pagina.
 *
 * La finestra non si chiude cliccando fuori né con Esc: solo con i suoi
 * pulsanti, e se c'è qualcosa di inserito chiede prima conferma (vedi
 * components/kk/finestra.blade.php). Il contenuto si costruisce solo a
 * finestra aperta, così alla chiusura sparisce, fotocamera compresa.
 */
abstract class Operazione extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public bool $aperto = false;

    /** C'è qualcosa che andrebbe perso chiudendo? Lo chiede la finestra prima di uscire. */
    abstract public function haDati(): bool;

    /** Riporta l'operazione allo stato iniziale. */
    abstract protected function azzera(): void;

    protected function mostra(): void
    {
        $this->resetErrorBag();
        $this->azzera();
        $this->aperto = true;
    }

    /** Uscita senza salvare: la conferma, se serve, l'ha già chiesta la finestra. */
    public function chiudi(): void
    {
        $this->aperto = false;
        $this->azzera();
    }

    /** Operazione registrata: si chiude e si avvisano le pagine aperte di aggiornarsi. */
    protected function completata(): void
    {
        $this->chiudi();
        $this->dispatch('operazione-completata');
    }
}
