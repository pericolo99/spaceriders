<?php

namespace App\Filament\Resources\Clienti\RelationManagers;

use App\Filament\Resources\Noleggi\NoleggioResource;
use App\Models\Noleggio;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class NoleggiRelationManager extends RelationManager
{
    protected static string $relationship = 'noleggi';

    protected static ?string $title = 'Noleggi';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return NoleggioResource::colonne($table->defaultSort('data_consegna', 'desc'), conCliente: false)
            ->recordUrl(fn (Noleggio $record): string => NoleggioResource::getUrl('view', ['record' => $record]));
    }
}
