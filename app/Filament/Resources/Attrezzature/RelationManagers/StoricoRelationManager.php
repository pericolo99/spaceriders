<?php

namespace App\Filament\Resources\Attrezzature\RelationManagers;

use App\Filament\Resources\Noleggi\NoleggioResource;
use App\Models\RigaNoleggio;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Chi ha avuto il pezzo e come l'ha riportato. */
class StoricoRelationManager extends RelationManager
{
    protected static string $relationship = 'righe';

    protected static ?string $title = 'Storico noleggi';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('noleggio.cliente'))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (RigaNoleggio $r): string => NoleggioResource::getUrl('view', ['record' => $r->noleggio_id]))
            ->columns([
                TextColumn::make('noleggio.cliente.nominativo')->label('Cliente')->weight('semibold'),
                TextColumn::make('noleggio.data_consegna')->label('Consegna')->date('d/m/Y'),
                TextColumn::make('rientrata_at')->label('Rientrato')->dateTime('d/m/Y')->placeholder('fuori adesso'),
                TextColumn::make('esito')->label('Esito')->badge()->placeholder('—'),
                TextColumn::make('note')->label('Note')->placeholder('')->wrap(),
            ]);
    }
}
