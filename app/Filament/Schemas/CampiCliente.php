<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * I campi del cliente, in due misure: quelli che servono al banco per
 * consegnare subito, e la scheda completa dell'ufficio.
 */
class CampiCliente
{
    /** Nome, cognome e un recapito: abbastanza per consegnare e per sollecitare un ritardo. */
    public static function essenziali(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('nome')->label('Nome')->required()->maxLength(255),
                TextInput::make('cognome')->label('Cognome')->required()->maxLength(255),
                TextInput::make('telefono')->label('Telefono')->tel()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
            ]),
        ];
    }

    public static function completi(): array
    {
        return [
            Section::make('Cliente')->schema(self::essenziali()),
            Section::make('Documento e residenza')
                ->columns(2)
                ->schema([
                    TextInput::make('codice_fiscale')
                        ->label('Codice fiscale')
                        ->maxLength(16)
                        ->dehydrateStateUsing(fn (?string $state) => $state ? mb_strtoupper(trim($state)) : null),
                    DatePicker::make('data_nascita')->label('Data di nascita')->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('luogo_nascita')->label('Luogo di nascita'),
                    TextInput::make('indirizzo')->label('Indirizzo'),
                    TextInput::make('citta')->label('Città'),
                    TextInput::make('cap')->label('CAP')->maxLength(10),
                    TextInput::make('localita')->label('Località'),
                ]),
            Textarea::make('note')->label('Note')->rows(3)->columnSpanFull(),
        ];
    }
}
