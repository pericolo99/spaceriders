<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lo stato del pezzo in magazzino, indipendente dal fatto che sia fuori a
 * noleggio: un pezzo «attivo» può essere disponibile o noleggiato.
 */
enum StatoAttrezzatura: string implements HasColor, HasLabel
{
    case Attiva = 'attiva';
    // Rientrato con un dubbio: non si noleggia finché qualcuno non lo guarda.
    case DaControllare = 'da_controllare';
    // Rotto, smarrito o mai riconsegnato (nel vecchio pannello: stato -1).
    case FuoriServizio = 'fuori_servizio';

    public function getLabel(): string
    {
        return match ($this) {
            self::Attiva => 'In servizio',
            self::DaControllare => 'Da controllare',
            self::FuoriServizio => 'Fuori servizio',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Attiva => 'success',
            self::DaControllare => 'gray',
            self::FuoriServizio => 'warning',
        };
    }
}
