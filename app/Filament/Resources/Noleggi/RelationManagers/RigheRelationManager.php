<?php

namespace App\Filament\Resources\Noleggi\RelationManagers;

use App\Enums\Esito;
use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
use App\Models\RigaNoleggio;
use App\Noleggi\Banco;
use App\Noleggi\BancoException;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * I pezzi del noleggio. Da qui si registra un rientro senza scanner, e si
 * segnala un pezzo che non torna (smarrito, mai riconsegnato).
 */
class RigheRelationManager extends RelationManager
{
    protected $listeners = ['operazione-completata' => '$refresh'];

    protected static string $relationship = 'righe';

    protected static ?string $title = 'Pezzi';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('attrezzatura')->orderByRaw('rientrata_at is not null')->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('attrezzatura.nome')
                    ->label('Pezzo')
                    ->weight('semibold')
                    ->description(fn (RigaNoleggio $r): string => $r->attrezzatura->tipologia->getLabel())
                    ->url(fn (RigaNoleggio $r): string => AttrezzaturaResource::getUrl('edit', ['record' => $r->attrezzatura_id])),
                TextColumn::make('attrezzatura.codice')->label('Codice')->fontFamily('mono')->color('gray'),
                TextColumn::make('rientrata_at')->label('Rientrato')->dateTime('d/m/Y H:i')->placeholder('fuori'),
                TextColumn::make('esito')->label('Esito')->badge()->placeholder('—'),
                TextColumn::make('note')->label('Note')->placeholder('')->wrap()->toggleable(),
            ])
            ->recordActions([
                Action::make('rientro')
                    ->label('Rientro')
                    ->icon('heroicon-m-arrow-left')
                    ->button()
                    ->visible(fn (RigaNoleggio $r): bool => $r->isAperta())
                    ->modalHeading(fn (RigaNoleggio $r): string => "Rientro di {$r->attrezzatura->nome}")
                    ->schema([
                        Radio::make('esito')
                            ->label('Com\'è tornato?')
                            ->options(collect([Esito::Integro, Esito::DaControllare, Esito::Danneggiato])
                                ->mapWithKeys(fn (Esito $e) => [$e->value => $e->getLabel()]))
                            ->default(Esito::Integro->value)
                            ->required(),
                        Textarea::make('note')->label('Note')->rows(2),
                    ])
                    ->action(fn (RigaNoleggio $r, array $data) => $this->chiudi($r, Esito::from($data['esito']), $data['note'] ?? null)),
                Action::make('segnala')
                    ->label('Non è tornato')
                    ->color('warning')
                    ->link()
                    ->visible(fn (RigaNoleggio $r): bool => $r->isAperta())
                    ->modalHeading(fn (RigaNoleggio $r): string => "{$r->attrezzatura->nome} non è tornato")
                    ->modalDescription('La riga si chiude e il pezzo esce dal servizio finché non lo rimetti in inventario.')
                    ->schema([
                        Radio::make('esito')
                            ->label('Che cosa è successo')
                            ->options(collect(Esito::senzaRientro())->mapWithKeys(fn (Esito $e) => [$e->value => $e->getLabel()]))
                            ->required(),
                        Textarea::make('note')->label('Note')->rows(2),
                    ])
                    ->action(fn (RigaNoleggio $r, array $data) => $this->chiudi($r, Esito::from($data['esito']), $data['note'] ?? null)),
            ]);
    }

    private function chiudi(RigaNoleggio $riga, Esito $esito, ?string $note): void
    {
        try {
            app(Banco::class)->rientro($riga, $esito, $note, operatore: Auth::user());
        } catch (BancoException $e) {
            $e->avviso()->danger()->send();

            return;
        }

        Notification::make()
            ->title($esito->presente() ? 'Rientro registrato' : 'Segnalazione registrata')
            ->body("{$riga->attrezzatura->nome} · {$esito->getLabel()}")
            ->success()
            ->send();
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
