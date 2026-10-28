<?php

namespace App\Livewire;

use App\Enums\StatoAttrezzatura;
use App\Filament\Resources\Noleggi\NoleggioResource;
use App\Filament\Schemas\CampiCliente;
use App\Livewire\Concerns\SuggerisceAttrezzatura;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Noleggi\Banco;
use App\Noleggi\BancoException;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Il banco in uscita: chi noleggia, fino a quando, e i pezzi letti uno per
 * uno — dalla fotocamera del telefono o dal lettore laser.
 */
class ConsegnaOperazione extends Operazione
{
    use SuggerisceAttrezzatura;

    /** @var array<string, mixed> */
    public ?array $dati = [];

    /** Il campo del codice: ci scrive il lettore laser o chi digita. */
    public string $codice = '';

    /** @var list<int> i pezzi letti, nell'ordine di lettura */
    public array $pezzi = [];

    /** Il cliente con cui si è aperta la finestra, se arrivava già scelto. */
    public ?int $clienteIniziale = null;

    #[On('apri-consegna')]
    public function apri(?int $cliente = null): void
    {
        $this->mostra();
        $this->clienteIniziale = $cliente;
        $this->form->fill([...$this->valoriIniziali(), 'cliente_id' => $cliente]);
    }

    public function haDati(): bool
    {
        return $this->pezzi !== []
            || ($this->dati['cliente_id'] ?? null) != $this->clienteIniziale
            || filled($this->dati['note'] ?? null);
    }

    protected function azzera(): void
    {
        $this->pezzi = [];
        $this->codice = '';
        $this->clienteIniziale = null;
        $this->dati = $this->valoriIniziali();
    }

    /** @return array<string, mixed> */
    private function valoriIniziali(): array
    {
        return [
            'cliente_id' => null,
            'data_consegna' => today()->toDateString(),
            'data_rientro' => today()->addDay()->toDateString(),
            'giorni_extra' => 0,
            'sacche_sci' => 0,
            'sacche_snow' => 0,
            'sacche_scarponi' => 0,
            'note' => null,
            'da_pagare' => false,
            'note_pagamento' => null,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('dati')
            ->components([
                // Sezioni senza riquadro: titoli e campi, Note comprese, partono
                // dallo stesso bordo, allineati anche con «Materiale» a destra.
                Section::make('Cliente e date')
                    ->contained(false)
                    ->schema([
                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->placeholder('Cerca per cognome, nome o telefono')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Cliente::query()
                                ->where(fn ($q) => $q
                                    ->where('cognome', 'like', "%{$search}%")
                                    ->orWhere('nome', 'like', "%{$search}%")
                                    ->orWhere('telefono', 'like', "%{$search}%")
                                    ->orWhere('codice_fiscale', 'like', "%{$search}%"))
                                ->orderBy('cognome')->orderBy('nome')
                                ->limit(30)->get()
                                ->mapWithKeys(fn (Cliente $c) => [$c->id => $c->nominativo.($c->telefono ? " · {$c->telefono}" : '')])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Cliente::find($value)?->nominativo)
                            ->createOptionForm(CampiCliente::essenziali())
                            ->createOptionUsing(fn (array $data): int => Cliente::create($data)->getKey())
                            ->createOptionModalHeading('Nuovo cliente'),
                        Grid::make(['default' => 2, 'md' => 3])->schema([
                            DatePicker::make('data_consegna')
                                ->label('Consegna')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->required(),
                            DatePicker::make('data_rientro')
                                ->label('Rientro')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->required()
                                ->afterOrEqual('data_consegna'),
                            TextInput::make('giorni_extra')
                                ->label('Giorni extra')
                                ->numeric()->integer()->minValue(0)->maxValue(365)
                                ->default(0),
                        ]),
                    ]),
                Section::make('Sacche consegnate')
                    ->contained(false)
                    ->description('Da farsi ridare al rientro.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextInput::make('sacche_sci')->label('Sci')->numeric()->integer()->minValue(0)->maxValue(20),
                        TextInput::make('sacche_snow')->label('Snowboard')->numeric()->integer()->minValue(0)->maxValue(20),
                        TextInput::make('sacche_scarponi')->label('Scarponi')->numeric()->integer()->minValue(0)->maxValue(20),
                    ]),
                // Il promemoria del vecchio pannello: al rientro il banco lo vede in rosso.
                Checkbox::make('da_pagare')
                    ->label('Da pagare al rientro')
                    ->live(),
                TextInput::make('note_pagamento')
                    ->label('Note sul pagamento')
                    ->placeholder('Es. paga con il gruppo, manca il saldo')
                    ->maxLength(255)
                    ->visible(fn ($get): bool => (bool) $get('da_pagare')),
                Textarea::make('note')
                    ->label('Note')
                    ->rows(2),
            ]);
    }

    /** Un codice letto: dalla fotocamera arriva come argomento, dal campo come proprietà. */
    public function aggiungi(?string $codice = null): void
    {
        $codice = trim($codice ?? $this->codice);
        $this->codice = '';

        if ($codice === '') {
            return;
        }

        try {
            $pezzo = app(Banco::class)->pezzoDaConsegnare($codice);
        } catch (BancoException $e) {
            $e->avviso()->send();

            return;
        }

        if (in_array($pezzo->id, $this->pezzi, true)) {
            Notification::make()->title('Già nell\'elenco')->body("{$pezzo->nome} è già tra i pezzi da consegnare.")->info()->send();

            return;
        }

        $this->pezzi[] = $pezzo->id;
    }

    /** In cima i pezzi che si possono consegnare; gli altri si vedono, con il motivo. */
    protected function suggerimenti(string $testo): array
    {
        return Attrezzatura::cerca($testo)
            ->where('stato', '!=', StatoAttrezzatura::FuoriServizio)
            ->with('rigaAperta.noleggio.cliente')
            ->orderBy('marca')->orderBy('modello')->orderBy('misura')
            ->limit(self::SUGGERIMENTI * 3)
            ->get()
            ->map(function (Attrezzatura $pezzo): array {
                [$stato, $colore, $scegli] = match (true) {
                    in_array($pezzo->id, $this->pezzi, true) => ['Già nell\'elenco', 'gray', false],
                    $pezzo->rigaAperta !== null => ['Fuori · '.$pezzo->rigaAperta->noleggio->cliente->nominativo, 'gray', false],
                    $pezzo->stato !== StatoAttrezzatura::Attiva => [$pezzo->stato->getLabel(), 'gray', false],
                    default => ['Disponibile', 'primary', true],
                };

                return [...$this->descrivi($pezzo), 'stato' => $stato, 'colore' => $colore, 'scegli' => $scegli];
            })
            ->sortByDesc('scegli')
            ->take(self::SUGGERIMENTI)
            ->values()
            ->all();
    }

    public function rimuovi(int $id): void
    {
        $this->pezzi = array_values(array_diff($this->pezzi, [$id]));
    }

    public function confermaAction(): Action
    {
        return Action::make('conferma')
            ->label(fn (): string => match (count($this->pezzi)) {
                0 => 'Scansiona i pezzi',
                1 => 'Conferma la consegna di 1 pezzo',
                default => 'Conferma la consegna di '.count($this->pezzi).' pezzi',
            })
            ->size('lg')
            // Larghezza fissa: l'etichetta cambia col numero di pezzi, il pulsante no.
            ->extraAttributes(['class' => 'sm:min-w-80'])
            ->disabled(fn (): bool => $this->pezzi === [])
            ->action(fn () => $this->conferma());
    }

    public function conferma(): void
    {
        $dati = $this->form->getState();

        try {
            $noleggio = app(Banco::class)->consegna(
                cliente: Cliente::findOrFail($dati['cliente_id']),
                attrezzature: $this->pezzi,
                dataConsegna: Carbon::parse($dati['data_consegna']),
                dataRientro: Carbon::parse($dati['data_rientro']),
                giorniExtra: (int) ($dati['giorni_extra'] ?? 0),
                sacche: [
                    'sci' => (int) ($dati['sacche_sci'] ?? 0),
                    'snow' => (int) ($dati['sacche_snow'] ?? 0),
                    'scarponi' => (int) ($dati['sacche_scarponi'] ?? 0),
                ],
                note: $dati['note'] ?? null,
                operatore: Auth::user(),
                daPagare: (bool) ($dati['da_pagare'] ?? false),
                notePagamento: $dati['note_pagamento'] ?? null,
            );
        } catch (BancoException $e) {
            $e->avviso()->danger()->send();

            return;
        }

        $pezzi = $noleggio->righe()->count();

        Notification::make()
            ->title('Consegna registrata')
            ->body(($pezzi === 1 ? '1 pezzo' : "{$pezzi} pezzi")." a {$noleggio->cliente->nominativo}, rientro il {$noleggio->data_rientro->format('d/m')}.")
            ->success()
            ->actions([
                Action::make('apri')->label('Apri il noleggio')->url(NoleggioResource::getUrl('view', ['record' => $noleggio])),
            ])
            ->send();

        $this->completata();
    }

    /** @return Collection<int, Attrezzatura> nell'ordine di lettura, l'ultimo in cima */
    public function getPezziLetti(): Collection
    {
        $pezzi = Attrezzatura::whereKey($this->pezzi)->get()->keyBy('id');

        return new Collection(array_reverse(array_values(array_filter(
            array_map(fn (int $id) => $pezzi->get($id), $this->pezzi)
        ))));
    }

    public function render(): View
    {
        return view('livewire.consegna');
    }
}
