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
        return [Action::make('nuovo')->label('Nuovo cliente')->dispatch('apri-nuovo-cliente')];
    }
}
