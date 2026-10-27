<?php

namespace App\Filament\Resources\Noleggi;

use App\Filament\Resources\Clienti\ClienteResource;
use App\Filament\Resources\Noleggi\Pages\EditNoleggio;
use App\Filament\Resources\Noleggi\Pages\ListNoleggi;
use App\Filament\Resources\Noleggi\Pages\ViewNoleggio;
use App\Filament\Resources\Noleggi\RelationManagers\RigheRelationManager;
use App\Models\Noleggio;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NoleggioResource extends Resource
{
    protected static ?string $model = Noleggio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Noleggi';

    protected static ?string $modelLabel = 'noleggio';

    protected static ?string $pluralModelLabel = 'noleggi';

    protected static ?string $slug = 'noleggi';

    /** Si correggono date, sacche e note; i pezzi passano solo da consegna e rientro. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                DatePicker::make('data_consegna')->label('Consegna')->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('data_rientro')->label('Rientro previsto')->native(false)->displayFormat('d/m/Y')->required()->afterOrEqual('data_consegna'),
                TextInput::make('giorni_extra')->label('Giorni extra')->numeric()->integer()->minValue(0),
                TextInput::make('sacche_sci')->label('Sacche sci')->numeric()->integer()->minValue(0),
                TextInput::make('sacche_snow')->label('Sacche snowboard')->numeric()->integer()->minValue(0),
                TextInput::make('sacche_scarponi')->label('Sacche scarponi')->numeric()->integer()->minValue(0),
                Textarea::make('note')->label('Note')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(['default' => 2, 'md' => 4])->schema([
                TextEntry::make('cliente.nominativo')->label('Cliente')->weight('semibold')
                    ->url(fn (Noleggio $record): string => ClienteResource::getUrl('edit', ['record' => $record->cliente_id])),
                TextEntry::make('cliente.telefono')->label('Telefono')->placeholder('—'),
                TextEntry::make('data_consegna')->label('Consegna')->date('d/m/Y'),
                TextEntry::make('data_rientro')->label('Rientro previsto')->date('d/m/Y')
                    ->color(fn (Noleggio $record): ?string => $record->isInRitardo() ? 'warning' : null)
                    ->helperText(fn (Noleggio $record): ?string => $record->isInRitardo() ? $record->giorniDiRitardo().' giorni di ritardo' : null),
                TextEntry::make('stato')->label('Stato')->badge()
                    ->state(fn (Noleggio $record): string => self::stato($record))
                    ->color(fn (Noleggio $record): string => self::coloreStato($record)),
                TextEntry::make('giorni_extra')->label('Giorni extra'),
                TextEntry::make('sacche')->label('Sacche')->state(fn (Noleggio $record): ?string => $record->sacche())->placeholder('nessuna'),
                TextEntry::make('operatore.name')->label('Consegnato da')->placeholder('—'),
                TextEntry::make('note')->label('Note')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return self::colonne($table->defaultSort('data_rientro'))
            ->recordActions([
                Action::make('rientro')
                    ->label('Rientro')
                    ->icon('heroicon-m-arrow-left')
                    ->button()
                    ->visible(fn (Noleggio $record): bool => $record->isAperto())
                    ->dispatch('apri-rientro-noleggio', fn (Noleggio $record): array => ['noleggio' => $record->getKey()]),
            ])
            ->filters([
                SelectFilter::make('stato')
                    ->label('Stato')
                    ->options(['aperti' => 'Aperti', 'ritardo' => 'In ritardo', 'chiusi' => 'Chiusi'])
                    ->default('aperti')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'aperti' => $query->aperti(),
                        'ritardo' => $query->inRitardo(),
                        'chiusi' => $query->chiusi(),
                        default => $query,
                    }),
                SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options([
                        'consegnati_oggi' => 'Consegnati oggi',
                        'rientro_oggi' => 'Rientro previsto oggi',
                        'stagione' => 'Questa stagione',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'consegnati_oggi' => $query->whereDate('data_consegna', today()),
                        'rientro_oggi' => $query->rientroOggi(),
                        // La stagione invernale parte a ottobre.
                        'stagione' => $query->whereDate('data_consegna', '>=', today()->month >= 10
                            ? today()->setDate(today()->year, 10, 1)
                            : today()->setDate(today()->year - 1, 10, 1)),
                        default => $query,
                    }),
            ]);
    }

    /** Le colonne comuni all'elenco e alla scheda del cliente. */
    public static function colonne(Table $table, bool $conCliente = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cliente')->withCount(['righe', 'righeAperte']))
            ->columns(array_values(array_filter([
                TextColumn::make('id')->label('N.')->sortable()->color('gray'),
                $conCliente ? TextColumn::make('cliente.nominativo')
                    ->label('Cliente')
                    ->weight('semibold')
                    ->searchable(['cognome', 'nome']) : null,
                TextColumn::make('data_consegna')->label('Consegna')->date('d/m/Y')->sortable(),
                TextColumn::make('data_rientro')->label('Rientro')->date('d/m/Y')->sortable()
                    ->color(fn (Noleggio $record): ?string => $record->isInRitardo() ? 'warning' : null)
                    ->weight(fn (Noleggio $record): ?string => $record->isInRitardo() ? 'semibold' : null),
                TextColumn::make('pezzi')->label('Pezzi')
                    ->state(fn (Noleggio $record): string => $record->righe_aperte_count && $record->righe_aperte_count < $record->righe_count
                        ? "{$record->righe_aperte_count} fuori su {$record->righe_count}"
                        : (string) $record->righe_count),
                TextColumn::make('stato')->label('Stato')->badge()
                    ->state(fn (Noleggio $record): string => self::stato($record))
                    ->color(fn (Noleggio $record): string => self::coloreStato($record)),
            ])));
    }

    public static function stato(Noleggio $noleggio): string
    {
        return match (true) {
            ! $noleggio->isAperto() => 'Chiuso',
            $noleggio->isInRitardo() => 'In ritardo',
            default => 'Aperto',
        };
    }

    public static function coloreStato(Noleggio $noleggio): string
    {
        return match (true) {
            ! $noleggio->isAperto() => 'gray',
            $noleggio->isInRitardo() => 'warning',
            default => 'primary',
        };
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Noleggio::inRitardo()->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Noleggi in ritardo';
    }

    public static function getRelations(): array
    {
        return [RigheRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNoleggi::route('/'),
            'view' => ViewNoleggio::route('/{record}'),
            'edit' => EditNoleggio::route('/{record}/modifica'),
        ];
    }
}
