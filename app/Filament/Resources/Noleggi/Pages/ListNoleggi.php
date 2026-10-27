<?php

namespace App\Filament\Resources\Noleggi\Pages;

use App\Filament\Resources\Noleggi\NoleggioResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListNoleggi extends ListRecords
{
    /** Dopo una consegna o un rientro l'elenco si aggiorna da solo. */
    protected $listeners = ['operazione-completata' => '$refresh'];

    protected static string $resource = NoleggioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('consegna')->label('Nuova consegna')->dispatch('apri-consegna'),
        ];
    }
}
