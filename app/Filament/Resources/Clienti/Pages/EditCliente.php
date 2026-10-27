<?php

namespace App\Filament\Resources\Clienti\Pages;

use App\Filament\Resources\Clienti\ClienteResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('consegna')
                ->label('Nuova consegna')
                ->dispatch('apri-consegna', fn (): array => ['cliente' => $this->getRecord()->getKey()]),
            // Un cliente con noleggi non si cancella: è lo storico di chi ha avuto cosa.
            DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->noleggi()->exists()),
        ];
    }
}
