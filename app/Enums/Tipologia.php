<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Le famiglie di attrezzatura. I valori sono i codici del vecchio pannello
 * (Kaukau\Costanti), così l'importazione non deve tradurli.
 */
enum Tipologia: string implements HasLabel
{
    case Sci = 'SKI';
    case Snowboard = 'BOARD';
    case ScarponiSci = 'SSK';
    case ScarponiSnowboard = 'SSB';
    case Casco = 'CSK';
    case Racchette = 'RCC';
    case Guanti = 'GNT';
    case Altro = 'ALT';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sci => 'Sci',
            self::Snowboard => 'Snowboard',
            self::ScarponiSci => 'Scarponi sci',
            self::ScarponiSnowboard => 'Scarponi snowboard',
            self::Casco => 'Casco',
            self::Racchette => 'Racchette',
            self::Guanti => 'Guanti',
            self::Altro => 'Altro',
        };
    }

    /** Il nome al plurale, per le schede dell'inventario. */
    public function plurale(): string
    {
        return match ($this) {
            self::Casco => 'Caschi',
            default => $this->getLabel(),
        };
    }
}
