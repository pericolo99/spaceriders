<?php

namespace App\Noleggi;

use DomainException;
use Filament\Notifications\Notification;

/**
 * Un'operazione al banco che non si può fare. Il titolo è l'etichetta breve
 * dell'avviso («Pezzo già fuori»), il messaggio il dettaglio per chi sta al banco.
 */
class BancoException extends DomainException
{
    public function __construct(public readonly string $titolo, string $dettaglio)
    {
        parent::__construct($dettaglio);
    }

    /** L'avviso da mostrare: etichetta in grassetto, dettaglio sotto. */
    public function avviso(): Notification
    {
        return Notification::make()->title($this->titolo)->body($this->getMessage())->warning();
    }
}
