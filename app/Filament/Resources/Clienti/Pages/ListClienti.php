<?php

namespace App\Filament\Resources\Clienti\Pages;

use App\Filament\Resources\Clienti\ClienteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListClienti extends ListRecords
{
    protected $listeners = ['operazione-completata' => '$refresh'];

    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // La pagina pubblica: da aprire sul tablet del banco o da stampare come QR.
            Action::make('registrazione')
                ->label('Pagina di registrazione')
                ->color('gray')
                ->url(fn (): string => route('filament.service.registrazione'))
                ->openUrlInNewTab(),
            Action::make('nuovo')->label('Nuovo cliente')->dispatch('apri-nuovo-cliente'),
        ];
    }
}
