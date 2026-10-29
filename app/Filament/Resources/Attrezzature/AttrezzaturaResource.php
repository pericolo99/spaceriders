<?php

namespace App\Filament\Resources\Attrezzature;

use App\Enums\StatoAttrezzatura;
use App\Enums\Tipologia;
use App\Filament\Resources\Attrezzature\Pages\CreateAttrezzatura;
use App\Filament\Resources\Attrezzature\Pages\EditAttrezzatura;
use App\Filament\Resources\Attrezzature\Pages\ListAttrezzature;
use App\Filament\Resources\Attrezzature\RelationManagers\StoricoRelationManager;
use App\Models\Attrezzatura;
use App\Noleggi\Banco;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttrezzaturaResource extends Resource
{
    protected static ?string $model = Attrezzatura::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Inventario';

    protected static ?string $modelLabel = 'attrezzatura';

    protected static ?string $pluralModelLabel = 'inventario';

    protected static ?string $slug = 'inventario';

    protected static ?string $recordTitleAttribute = 'codice';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(['default' => 1, 'md' => 3])->schema([
                TextInput::make('codice')
                    ->label('Codice a barre')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('codice_gestionale')->label('Codice gestionale')->maxLength(255),
                Select::make('tipologia')->label('Tipologia')->options(Tipologia::class)->required()->native(false),
                TextInput::make('marca')->label('Marca')->datalist(fn () => Attrezzatura::query()->distinct()->orderBy('marca')->pluck('marca')->filter()->all()),
                TextInput::make('modello')->label('Modello'),
                TextInput::make('misura')->label('Misura')->helperText('Lunghezza in cm, numero di scarpa o taglia.'),
                Select::make('stato')->label('Stato')->options(StatoAttrezzatura::class)->default(StatoAttrezzatura::Attiva)->required()->native(false),
                TextInput::make('descrizione')->label('Descrizione')->columnSpan(['md' => 2]),
                // Acquisto e prezzi sulla stessa riga.
                DatePicker::make('data_acquisto')->label('Acquistato il')->native(false)->displayFormat('d/m/Y'),
                TextInput::make('prezzo_acquisto')->label('Prezzo di acquisto')->numeric()->minValue(0)->step(0.01)->prefix('€'),
                TextInput::make('prezzo_vendita')->label('Prezzo di vendita')->numeric()->minValue(0)->step(0.01)->prefix('€'),
                Textarea::make('note')->label('Note')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Foto del prodotto')
                ->description('Compare nei suggerimenti mentre si digita il codice. Senza foto si vede l\'illustrazione della tipologia.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    FileUpload::make('foto')
                        ->label('Foto')
                        ->image()
                        ->disk('public')
                        ->directory('attrezzature')
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800'),
                    Toggle::make('foto_per_modello')
                        ->label('Usala per tutti i pezzi dello stesso modello')
                        ->helperText('Stessa marca e stesso modello, di ogni misura.')
                        // Acceso anche in modifica; agisce solo se la foto è cambiata.
                        ->afterStateHydrated(fn (Toggle $component) => $component->state(true))
                        ->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('rigaAperta.noleggio.cliente')->withCount('righe'))
            ->defaultSort('codice')
            ->groups([
                Group::make('modello')
                    ->label('Modello')
                    ->getTitleFromRecordUsing(fn (Attrezzatura $a): string => trim("{$a->marca} {$a->modello}") ?: 'Senza modello'),
                Group::make('misura')->label('Misura'),
            ])
            ->columns([
                ImageColumn::make('immagine')
                    ->label('')
                    ->state(fn (Attrezzatura $a): string => $a->immagine())
                    ->imageSize(44)
                    ->extraImgAttributes(fn (Attrezzatura $a): array => ['class' => 'rounded-lg object-contain p-1 '.$a->sfondoImmagine(), 'loading' => 'lazy']),
                TextColumn::make('codice')->label('Codice')->fontFamily('mono')->searchable()->sortable()->color('gray'),
                TextColumn::make('nome')
                    ->label('Attrezzatura')
                    ->state(fn (Attrezzatura $a): string => trim("{$a->marca} {$a->modello}") ?: '—')
                    ->description(fn (Attrezzatura $a): string => $a->tipologia->getLabel())
                    ->weight('semibold')
                    ->searchable(['marca', 'modello', 'codice_gestionale'])
                    ->sortable(['marca', 'modello']),
                TextColumn::make('misura')->label('Misura')->sortable()->placeholder('—'),
                TextColumn::make('dove')
                    ->label('Dov\'è')
                    ->badge()
                    ->state(fn (Attrezzatura $a): string => match (true) {
                        $a->rigaAperta !== null => 'Fuori · '.$a->rigaAperta->noleggio->cliente->nominativo,
                        $a->stato === StatoAttrezzatura::Attiva => 'Disponibile',
                        default => $a->stato->getLabel(),
                    })
                    ->color(fn (Attrezzatura $a): string => match (true) {
                        $a->rigaAperta !== null => 'info',
                        default => $a->stato->getColor(),
                    }),
                // Nell'elenco solo il prezzo di vendita; l'acquisto sta nella scheda.
                TextColumn::make('prezzo_vendita')->label('Prezzo')->money('EUR', locale: 'it')->sortable()->placeholder('—')->toggleable(),
                TextColumn::make('righe_count')->label('Noleggi')->numeric()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('disponibilita')
                    ->label('Disponibilità')
                    ->options(['disponibili' => 'Disponibili', 'fuori' => 'Fuori a noleggio'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'disponibili' => $query->disponibili(),
                        'fuori' => $query->fuori(),
                        default => $query,
                    }),
                SelectFilter::make('marca')
                    ->label('Marca')
                    ->options(fn () => Attrezzatura::query()->whereNotNull('marca')->distinct()->orderBy('marca')->pluck('marca', 'marca')->all())
                    ->searchable(),
            ])
            ->recordActions([
                self::rimettiInServizio(),
                EditAction::make(),
            ]);
    }

    public static function rimettiInServizio(): Action
    {
        return Action::make('rimetti')
            ->label('Rimetti in servizio')
            ->icon('heroicon-m-arrow-path')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Il pezzo torna noleggiabile e le sue segnalazioni aperte si chiudono.')
            ->visible(fn (Attrezzatura $record): bool => $record->stato !== StatoAttrezzatura::Attiva)
            ->action(function (Attrezzatura $record) {
                app(Banco::class)->rimettiInServizio($record);
                Notification::make()->title('Di nuovo in servizio')->body($record->nome)->success()->send();
            });
    }

    /**
     * Dopo il salvataggio: la stessa foto a tutti i pezzi di quel modello, se
     * richiesto e solo se la foto è nuova — salvare un pezzo per correggerne la
     * misura non deve toccare le foto degli altri.
     */
    public static function propagaFoto(Attrezzatura $pezzo, bool $perModello, bool $nuovo = false): int
    {
        $fotoNuova = $nuovo || $pezzo->wasChanged('foto');

        if (! $perModello || ! $fotoNuova || ! $pezzo->foto || ! $pezzo->marca || ! $pezzo->modello) {
            return 0;
        }

        return Attrezzatura::query()
            ->whereKeyNot($pezzo->getKey())
            ->where('marca', $pezzo->marca)
            ->where('modello', $pezzo->modello)
            ->update(['foto' => $pezzo->foto]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['codice', 'codice_gestionale', 'marca', 'modello'];
    }

    public static function getRelations(): array
    {
        return [StoricoRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttrezzature::route('/'),
            'create' => CreateAttrezzatura::route('/nuova'),
            'edit' => EditAttrezzatura::route('/{record}'),
        ];
    }
}
