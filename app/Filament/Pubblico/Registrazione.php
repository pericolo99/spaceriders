<?php

namespace App\Filament\Pubblico;

use App\Models\Cliente;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * La pagina pubblica dove il cliente scrive da sé i propri dati prima di
 * passare al banco: nel vecchio pannello era /registrazione, e ci si sono
 * registrati quasi nove clienti su dieci. Il banco poi lo trova per nome.
 *
 * Non sta in app/Filament/Pages perché quelle il pannello le scopre da sé e
 * le mette dietro l'accesso: questa la registra ServicePanelProvider::routes().
 */
class Registrazione extends SimplePage
{
    use WithRateLimiting;

    protected string $view = 'filament.pubblico.registrazione';

    /** @var array<string, mixed> */
    public ?array $dati = [];

    /** Il nome di chi si è appena registrato: la pagina mostra il grazie. */
    public ?string $registrato = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Registrazione';
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->registrato ? null : 'Registrazione';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->registrato ? null : 'Scrivi qui i tuoi dati prima di passare al banco: il noleggio sarà più veloce.';
    }

    public function getMaxWidth(): Width|string|null
    {
        return Width::TwoExtraLarge;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('dati')
            ->components([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('nome')->label('Nome')->required()->maxLength(255)->autocomplete('given-name'),
                    TextInput::make('cognome')->label('Cognome')->required()->maxLength(255)->autocomplete('family-name'),
                    TextInput::make('codice_fiscale')
                        ->label('Codice fiscale')
                        ->required()
                        ->length(16)
                        ->regex('/^[A-Za-z0-9]{16}$/')
                        ->validationMessages([
                            'regex' => 'Il codice fiscale ha 16 caratteri, solo lettere e numeri.',
                        ])
                        ->extraInputAttributes(['class' => 'uppercase', 'autocapitalize' => 'characters', 'spellcheck' => 'false'])
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state ? mb_strtoupper(trim($state)) : null)
                        ->columnSpanFull(),
                    // Sul telefono il selettore nativo della data è il più comodo.
                    DatePicker::make('data_nascita')
                        ->label('Data di nascita')
                        ->required()
                        ->maxDate(today()),
                    TextInput::make('luogo_nascita')->label('Luogo di nascita')->required()->maxLength(255),
                    TextInput::make('telefono')->label('Cellulare')->tel()->required()->maxLength(255)->autocomplete('tel'),
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(255)->autocomplete('email'),
                    TextInput::make('indirizzo')->label('Indirizzo')->maxLength(255)->autocomplete('street-address')->columnSpanFull(),
                    TextInput::make('citta')->label('Città')->required()->maxLength(255)->autocomplete('address-level2'),
                    Grid::make(2)->schema([
                        TextInput::make('cap')->label('CAP')->maxLength(10)->autocomplete('postal-code'),
                        TextInput::make('localita')->label('Località')->maxLength(255),
                    ]),
                    Checkbox::make('consenso')
                        ->label(fn (): HtmlString => self::etichettaConsenso())
                        ->accepted()
                        ->validationMessages(['accepted' => 'Per registrarti devi accettare termini e trattamento dei dati.'])
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('registra')
                ->footer([
                    Actions::make([
                        Action::make('registra')->label('Registrati')->submit('registra'),
                    ])->fullWidth(),
                ]),
        ]);
    }

    public function registra(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $e) {
            Notification::make()
                ->title('Troppi tentativi')
                ->body("Riprova fra {$e->minutesUntilAvailable} minuti, oppure registrati al banco.")
                ->danger()
                ->send();

            return;
        }

        $dati = $this->form->getState();

        // Come nel vecchio pannello: un codice fiscale già presente non si
        // registra due volte (e non si sovrascrive: chiunque potrebbe farlo).
        if (Cliente::where('codice_fiscale', $dati['codice_fiscale'])->exists()) {
            $this->addError('dati.codice_fiscale', 'Risulti già registrato: al banco basta dire nome e cognome.');

            return;
        }

        unset($dati['consenso']);

        $cliente = Cliente::create([...$dati, 'origine' => 'portale', 'consenso_at' => now()]);

        $this->registrato = $cliente->nome;
    }

    /** Per i familiari: si ricomincia con il modulo vuoto. */
    public function altraPersona(): void
    {
        $this->registrato = null;
        $this->form->fill();
    }

    private static function etichettaConsenso(): HtmlString
    {
        $testo = 'Accetto i termini e le condizioni d’uso e il trattamento dei miei dati personali';
        $privacy = config('app.privacy_url');

        return new HtmlString(e($testo).($privacy
            ? ' (<a href="'.e($privacy).'" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline">informativa</a>)'
            : ''));
    }
}
