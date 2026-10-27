<?php

namespace App\Livewire;

use App\Enums\Esito;
use App\Livewire\Concerns\SuggerisceAttrezzatura;
use App\Models\Attrezzatura;
use App\Models\Noleggio;
use App\Models\RigaNoleggio;
use App\Noleggi\Banco;
use App\Noleggi\BancoException;
use App\Support\Foto;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Il rientro. Si parte da un pezzo letto (o cercato) oppure da un noleggio
 * della lista «Da far rientrare»: in entrambi i casi compare tutto quello che
 * c'era in quel noleggio, e si smarca pezzo per pezzo — leggendolo o toccando
 * il suo esito. Un pezzo senza scelta resta fuori.
 */
class RientroOperazione extends Operazione
{
    use SuggerisceAttrezzatura;
    use WithFileUploads;

    public const FOTO_PER_PEZZO = 6;

    public ?int $noleggioId = null;

    /** @var array<int, string> id riga → esito scelto; le righe senza voce restano fuori */
    public array $esiti = [];

    /** @var array<int, string> id riga → nota, per gli esiti diversi da «a posto» */
    public array $note = [];

    /** Il campo del codice: leggere un pezzo lo segna «a posto». */
    public string $codice = '';

    /** @var array<int, list<TemporaryUploadedFile>> id riga → foto del danno, in attesa del salvataggio */
    public array $foto = [];

    /**
     * @var array<int, list<TemporaryUploadedFile>> le foto appena scelte: si aggiungono
     *                                              a $foto invece di sostituirle (il selettore di file ne restituisce solo l'ultima scelta).
     */
    public array $nuove = [];

    /** Senza noleggio si parte dalla lettura di un pezzo; con un noleggio, dalla sua lista. */
    #[On('apri-rientro')]
    #[On('apri-rientro-noleggio')]
    public function apri(?int $noleggio = null): void
    {
        $this->mostra();
        $this->noleggioId = $noleggio;
    }

    /** Si torna alla lettura per un altro cliente; solo se non si è ancora smarcato nulla. */
    public function cambiaNoleggio(): void
    {
        if ($this->esiti === []) {
            $this->azzera();
        }
    }

    public function haDati(): bool
    {
        return $this->esiti !== [];
    }

    protected function azzera(): void
    {
        $this->noleggioId = null;
        $this->esiti = [];
        $this->note = [];
        $this->codice = '';
        $this->foto = [];
        $this->nuove = [];
    }

    /** Le scelte di ogni pezzo, nell'ordine dei pulsanti. */
    public static function scelte(): array
    {
        return [
            Esito::Integro->value => 'A posto',
            Esito::DaControllare->value => 'Da controllare',
            Esito::Danneggiato->value => 'Danneggiato',
            Esito::NonRiconsegnato->value => 'Non è tornato',
        ];
    }

    /** Tocca una scelta; ritoccarla la toglie e il pezzo resta fuori. */
    public function scegli(int $riga, string $esito): void
    {
        if (! array_key_exists($esito, self::scelte()) || ! $this->righeAperte()->contains('id', $riga)) {
            return;
        }

        if (($this->esiti[$riga] ?? null) === $esito) {
            unset($this->esiti[$riga], $this->note[$riga], $this->foto[$riga]);

            return;
        }

        $this->esiti[$riga] = $esito;

        // Nota e foto servono solo a un pezzo che torna con un problema.
        if (! self::conFoto($esito)) {
            unset($this->foto[$riga]);
        }

        if ($esito === Esito::Integro->value) {
            unset($this->note[$riga]);
        }
    }

    /** Le foto hanno senso solo se il pezzo è al banco e ha qualcosa da mostrare. */
    public static function conFoto(?string $esito): bool
    {
        return in_array($esito, [Esito::DaControllare->value, Esito::Danneggiato->value], true);
    }

    /** Le foto appena scelte per una riga si aggiungono a quelle già presenti. */
    public function updatedNuove(mixed $valore, string $riga): void
    {
        $this->validate(
            ["nuove.{$riga}.*" => ['image', 'max:25600']],
            [],
            ["nuove.{$riga}.*" => 'foto'],
        );

        $this->foto[$riga] = array_slice(
            [...($this->foto[$riga] ?? []), ...array_values((array) $valore)],
            0,
            self::FOTO_PER_PEZZO,
        );

        unset($this->nuove[$riga]);
    }

    public function togliFoto(int $riga, int $indice): void
    {
        unset($this->foto[$riga][$indice]);
        $this->foto[$riga] = array_values($this->foto[$riga] ?? []);
    }

    public function tuttiAPosto(): void
    {
        foreach ($this->righeAperte() as $riga) {
            $this->esiti[$riga->id] = Esito::Integro->value;
        }
    }

    /**
     * Un codice letto, digitato o scelto dai suggerimenti: il pezzo va «a posto».
     * Se non c'è ancora un noleggio aperto, si apre il suo. Un pezzo di un altro
     * noleggio fa cambiare cliente solo se qui non si è ancora smarcato nulla.
     */
    public function leggi(?string $codice = null): void
    {
        $codice = trim($codice ?? $this->codice);
        $this->codice = '';

        if ($codice === '') {
            return;
        }

        try {
            $riga = app(Banco::class)->rigaDaRientrare($codice);
        } catch (BancoException $e) {
            $e->avviso()->send();

            return;
        }

        if ($this->noleggioId && $riga->noleggio_id !== $this->noleggioId) {
            if ($this->esiti !== []) {
                Notification::make()
                    ->title('È di un altro noleggio')
                    ->body("{$riga->attrezzatura->nome} è di {$riga->noleggio->cliente->nominativo}: registra prima questo rientro.")
                    ->warning()
                    ->send();

                return;
            }

            $this->azzera();
        }

        $this->noleggioId = $riga->noleggio_id;
        $this->esiti[$riga->id] = Esito::Integro->value;
    }

    /** Prima di scegliere il noleggio: i pezzi fuori adesso, con chi li ha e quando devono tornare. */
    protected function suggerimenti(string $testo): array
    {
        return Attrezzatura::cerca($testo)
            ->fuori()
            ->with('rigaAperta.noleggio.cliente')
            ->orderBy('marca')->orderBy('modello')->orderBy('misura')
            ->limit(self::SUGGERIMENTI)
            ->get()
            ->map(function (Attrezzatura $pezzo): array {
                $noleggio = $pezzo->rigaAperta->noleggio;
                $quando = $noleggio->isInRitardo()
                    ? $noleggio->giorniDiRitardo().' gg di ritardo'
                    : 'rientro '.$noleggio->data_rientro->format('d/m');

                return [
                    ...$this->descrivi($pezzo),
                    'stato' => $noleggio->cliente->nominativo.' · '.$quando,
                    'colore' => $noleggio->isInRitardo() ? 'warning' : 'gray',
                    'scegli' => true,
                ];
            })
            ->all();
    }

    public function registraAction(): Action
    {
        return Action::make('registra')
            ->label(fn (): string => match (count($this->esiti)) {
                0 => 'Scegli i pezzi che rientrano',
                1 => 'Registra 1 rientro',
                default => 'Registra '.count($this->esiti).' rientri',
            })
            ->size('lg')
            ->extraAttributes(['class' => 'sm:min-w-80'])
            ->disabled(fn (): bool => $this->esiti === [])
            ->action(fn () => $this->registra());
    }

    public function registra(): void
    {
        $noleggio = $this->getNoleggio();
        $righe = $this->righeAperte()->whereIn('id', array_keys($this->esiti));

        if (! $noleggio || $righe->isEmpty()) {
            return;
        }

        try {
            DB::transaction(function () use ($righe) {
                foreach ($righe as $riga) {
                    $esito = $this->esiti[$riga->id];

                    app(Banco::class)->rientro(
                        riga: $riga,
                        esito: Esito::from($esito),
                        note: filled($this->note[$riga->id] ?? null) ? $this->note[$riga->id] : null,
                        foto: self::conFoto($esito)
                            ? array_map(fn ($f) => Foto::salva($f, 'segnalazioni'), $this->foto[$riga->id] ?? [])
                            : [],
                        operatore: Auth::user(),
                    );
                }
            });
        } catch (BancoException $e) {
            $e->avviso()->danger()->send();

            return;
        }

        $noleggio->refresh();
        $n = $righe->count();

        Notification::make()
            ->title($noleggio->isAperto() ? 'Rientro registrato' : 'Noleggio completato')
            ->body(($n === 1 ? '1 pezzo' : "{$n} pezzi")." di {$noleggio->cliente->nominativo}"
                .($noleggio->isAperto() ? ', altri '.$noleggio->righeAperte()->count().' ancora fuori.' : ': tutto rientrato.'))
            ->success()
            ->send();

        $this->completata();
    }

    public function getNoleggio(): ?Noleggio
    {
        return $this->noleggioId ? Noleggio::with('cliente')->find($this->noleggioId) : null;
    }

    /** @return Collection<int, RigaNoleggio> */
    public function righeAperte(): Collection
    {
        return $this->noleggioId
            ? RigaNoleggio::with('attrezzatura')->where('noleggio_id', $this->noleggioId)->whereNull('rientrata_at')->orderBy('id')->get()
            : collect();
    }

    /** @return Collection<int, RigaNoleggio> */
    public function righeRientrate(): Collection
    {
        return $this->noleggioId
            ? RigaNoleggio::with('attrezzatura')->where('noleggio_id', $this->noleggioId)->whereNotNull('rientrata_at')->latest('rientrata_at')->get()
            : collect();
    }

    public function render(): View
    {
        return view('livewire.rientro');
    }
}
