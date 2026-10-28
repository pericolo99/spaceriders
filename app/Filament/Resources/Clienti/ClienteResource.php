<?php

namespace App\Filament\Resources\Clienti;

use App\Filament\Resources\Clienti\Pages\EditCliente;
use App\Filament\Resources\Clienti\Pages\ListClienti;
use App\Filament\Resources\Clienti\RelationManagers\NoleggiRelationManager;
use App\Filament\Schemas\CampiCliente;
use App\Models\Cliente;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Clienti';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clienti';

    protected static ?string $slug = 'clienti';

    protected static ?string $recordTitleAttribute = 'nominativo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(CampiCliente::completi())->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'noleggi',
                'noleggi as noleggi_aperti_count' => fn (Builder $n) => $n->aperti(),
            ]))
            ->defaultSort('cognome')
            ->columns([
                TextColumn::make('nominativo')
                    ->label('Cliente')
                    ->weight('semibold')
                    ->searchable(['cognome', 'nome', 'codice_fiscale'])
                    ->sortable(['cognome', 'nome'])
                    ->description(fn (Cliente $c): ?string => $c->codice_fiscale),
                TextColumn::make('origine')
                    ->label('Registrato')
                    ->formatStateUsing(fn (string $state): string => $state === 'portale' ? 'Da solo' : 'Al banco')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('telefono')->label('Telefono')->searchable()->placeholder('—'),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('citta')->label('Città')->placeholder('—')->toggleable(),
                TextColumn::make('noleggi_count')->label('Noleggi')->numeric()->sortable(),
                TextColumn::make('noleggi_aperti_count')
                    ->label('Fuori adesso')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (int $state): string => $state ? "{$state} aperti" : '')
                    ->placeholder(''),
            ])
            ->filters([
                SelectFilter::make('origine')
                    ->label('Registrato')
                    ->options(['portale' => 'Da solo, dalla pagina di registrazione', 'banco' => 'Al banco']),
                TernaryFilter::make('con_noleggi_aperti')
                    ->label('Noleggi aperti')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('noleggi', fn (Builder $n) => $n->aperti()),
                        false: fn (Builder $query) => $query->whereDoesntHave('noleggi', fn (Builder $n) => $n->aperti()),
                    ),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['cognome', 'nome', 'telefono', 'codice_fiscale'];
    }

    public static function getRelations(): array
    {
        return [NoleggiRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClienti::route('/'),
            'edit' => EditCliente::route('/{record}'),
        ];
    }
}
