<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Come si chiude il giro di un pezzo. Le prime tre sono le risposte a
 * «Com'è tornato?» al banco; le altre si usano quando il pezzo non torna.
 * Le causali del vecchio pannello (SMR, RTT, NNC, ALT) finiscono qui.
 */
enum Esito: string implements HasColor, HasLabel
{
    case Integro = 'integro';
    case DaControllare = 'da_controllare';
    case Danneggiato = 'danneggiato';
    case Smarrito = 'smarrito';
    case NonRiconsegnato = 'non_riconsegnato';
    case Altro = 'altro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Integro => 'Tutto a posto',
            self::DaControllare => 'Da controllare',
            self::Danneggiato => 'Danneggiato',
            self::Smarrito => 'Smarrito',
            self::NonRiconsegnato => 'Non riconsegnato',
            self::Altro => 'Altro',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Integro => 'success',
            self::DaControllare => 'gray',
            default => 'warning',
        };
    }

    /** Lo stato in cui finisce il pezzo dopo questo esito. */
    public function statoAttrezzatura(): StatoAttrezzatura
    {
        return match ($this) {
            self::Integro => StatoAttrezzatura::Attiva,
            self::DaControllare => StatoAttrezzatura::DaControllare,
            default => StatoAttrezzatura::FuoriServizio,
        };
    }

    /** Il pezzo è fisicamente al banco? Serve a decidere che cosa chiedere. */
    public function presente(): bool
    {
        return ! in_array($this, [self::Smarrito, self::NonRiconsegnato], true);
    }

    /** Le scelte che si offrono quando il pezzo non torna. */
    public static function senzaRientro(): array
    {
        return [self::Smarrito, self::NonRiconsegnato, self::Danneggiato, self::Altro];
    }

    public static function daCausaleStorica(?string $causale): self
    {
        return match ($causale) {
            'SMR' => self::Smarrito,
            'RTT' => self::Danneggiato,
            'NNC' => self::NonRiconsegnato,
            default => self::Altro,
        };
    }
}
