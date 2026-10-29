<?php

namespace App\Console\Commands;

use App\Enums\Esito;
use App\Enums\StatoAttrezzatura;
use App\Enums\Tipologia;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Models\Noleggio;
use App\Models\Segnalazione;
use App\Support\Nominativo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Copia i dati del vecchio pannello (database `storico`) nelle tabelle nuove.
 *
 * Si può rilanciare: clienti, pezzi, noleggi e segnalazioni si aggiornano per
 * `storico_id`; le righe di un noleggio si scrivono solo la prima volta, così
 * un rientro fatto nel nuovo pannello non viene mai sovrascritto.
 *
 * Dei pezzi si importano anche i prezzi di acquisto e di vendita.
 * Non si importano i pagamenti né le tabelle che il vecchio pannello non
 * usava (prenotazioni, borsellino, buoni, tracing): sono vuote nel dump.
 */
class ImportaStorico extends Command
{
    protected $signature = 'kaukau:importa-storico {--connessione=storico : la connessione al database del vecchio pannello}';

    protected $description = 'Importa utenti, clienti, attrezzature, noleggi e segnalazioni dal vecchio pannello';

    private ConnectionInterface $storico;

    /** @var array<int, int> id storico → id cliente */
    private array $clienti = [];

    /** @var array<string, int> codice → id attrezzatura */
    private array $pezzi = [];

    /** @var array<int, int> id storico → id noleggio */
    private array $noleggi = [];

    /** @var array<int, true> i noleggi creati da questa importazione */
    private array $nuovi = [];

    public function handle(): int
    {
        $this->storico = DB::connection($this->option('connessione'));

        DB::transaction(function () {
            $this->utenti();
            $this->clienti();
            $this->attrezzature();
            $this->noleggi();
            $this->segnalazioni();
            $this->chiusure();
        });

        $this->newLine();
        $this->info('Importazione completata.');

        return self::SUCCESS;
    }

    /** Gli hash del vecchio pannello sono bcrypt ($2y$): le password restano quelle. */
    private function utenti(): void
    {
        $n = 0;
        $senzaPassword = [];

        foreach ($this->storico->table('users')->get() as $u) {
            $bcrypt = str_starts_with((string) $u->password, '$2y$') || str_starts_with((string) $u->password, '$2a$');

            if (! $bcrypt) {
                $senzaPassword[] = $u->email;
            }

            // Query builder e non modello: il cast «hashed» ripasserebbe l'hash.
            DB::table('users')->updateOrInsert(
                ['email' => mb_strtolower(trim($u->email))],
                [
                    'storico_id' => $u->id,
                    'name' => Nominativo::capitalizza(($u->first_name ?? '').' '.($u->last_name ?? '')) ?? $u->email,
                    'password' => $bcrypt ? $u->password : bcrypt(str()->random(40)),
                    'created_at' => $u->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );
            $n++;
        }

        $this->line("Utenti: {$n}");

        foreach ($senzaPassword as $email) {
            $this->warn("  {$email}: la password non era in un formato riconosciuto, va reimpostata.");
        }
    }

    private function clienti(): void
    {
        $n = 0;

        foreach ($this->storico->table('anagrafica')->orderBy('id')->cursor() as $a) {
            $cliente = Cliente::updateOrCreate(['storico_id' => $a->id], [
                'nome' => $this->testo($a->nome),
                'cognome' => $this->testo($a->cognome),
                'codice_fiscale' => $this->testo($a->codice_fiscale) ? mb_strtoupper(trim($a->codice_fiscale)) : null,
                'data_nascita' => $this->data($a->data_nascita),
                'luogo_nascita' => $this->testo($a->luogo_nascita),
                'indirizzo' => $this->testo($a->indirizzo),
                'citta' => $this->testo($a->citta),
                'cap' => $this->testo($a->cap),
                'localita' => $this->testo($a->localita),
                'telefono' => $this->testo($a->contatto_telefono),
                'email' => $this->testo($a->contatto_email),
                'note' => $this->testo($a->descrizione),
                'origine' => $a->registrazione === 'Portale' ? 'portale' : 'banco',
                'created_at' => $a->created_at ?? $this->data($a->data_creazione) ?? now(),
            ]);

            $this->clienti[$a->id] = $cliente->id;
            $n++;
        }

        $this->line("Clienti: {$n}");
    }

    private function attrezzature(): void
    {
        $n = 0;

        foreach ($this->storico->table('attrezzature')->orderBy('id')->cursor() as $a) {
            $codice = trim((string) $a->codice);

            if ($codice === '') {
                continue;
            }

            $pezzo = Attrezzatura::updateOrCreate(['storico_id' => $a->id], [
                'codice' => $codice,
                'codice_gestionale' => $this->testo($a->codice_gestionale),
                'tipologia' => Tipologia::tryFrom(trim((string) $a->tipologia)) ?? Tipologia::Altro,
                'marca' => $this->testo($a->marca),
                'modello' => $this->testo($a->modello),
                'misura' => $this->testo($a->misura),
                'descrizione' => $this->testo($a->descrizione),
                'stato' => (string) $a->stato === '-1' ? StatoAttrezzatura::FuoriServizio : StatoAttrezzatura::Attiva,
                'data_acquisto' => $this->data($a->data_acquisto),
                'prezzo_acquisto' => $this->prezzo($a->prezzo_acquisto),
                'prezzo_vendita' => $this->prezzo($a->prezzo_vendita),
                'note' => $this->testo($a->note),
                'created_at' => $a->created_at ?? now(),
            ]);

            $this->pezzi[$codice] = $pezzo->id;
            $n++;
        }

        $this->line("Attrezzature: {$n}");
    }

    private function noleggi(): void
    {
        $fuori = $this->storico->table('sessione_noleggio_attrezzature')->get()->groupBy('id_prenotazione');
        $rientri = $this->storico->table('sessione_noleggio_rientro')->get()->groupBy('id_sessione');
        // Gli esiti dei pezzi segnalati, per chiudere la loro riga con l'esito giusto.
        $esiti = $this->storico->table('stato_attrezzature')->get()
            ->mapWithKeys(fn ($s) => [$s->id_sessione.'|'.trim($s->codice) => Esito::daCausaleStorica($s->causale)]);

        $n = 0;
        $conRighe = 0;
        $pezziPrima = count(array_unique($this->pezzi));

        foreach ($this->storico->table('sessione_noleggio')->orderBy('id')->cursor() as $s) {
            if (! isset($this->clienti[$s->id_anagrafica])) {
                $this->warn("  Noleggio storico {$s->id}: cliente {$s->id_anagrafica} assente, saltato.");

                continue;
            }

            $noleggio = Noleggio::updateOrCreate(['storico_id' => $s->id], [
                'cliente_id' => $this->clienti[$s->id_anagrafica],
                'data_consegna' => $s->data_consegna,
                'data_rientro' => $s->data_rientro,
                'giorni_extra' => max(0, (int) $s->extra_gg),
                'sacche_sci' => (int) $s->sacche_sci,
                'sacche_snow' => (int) $s->sacche_snow,
                'sacche_scarponi' => (int) $s->sacche_scarponi,
                'note' => $this->testo($s->note),
                'created_at' => $s->created_at ?? $s->data_consegna,
            ]);

            // Il promemoria si importa solo la prima volta: se nel pannello nuovo
            // qualcuno ha già segnato «pagato», una nuova importazione non lo riaccende.
            if ($noleggio->wasRecentlyCreated) {
                $this->nuovi[$noleggio->id] = true;
                $noleggio->update([
                    'da_pagare' => $s->pagamento_consegna === 'on',
                    'note_pagamento' => $this->testo($s->note_pagamento),
                ]);
            }

            $this->noleggi[$s->id] = $noleggio->id;
            $n++;

            if ($noleggio->righe()->exists()) {
                continue;
            }

            $righe = [];

            foreach ($rientri->get($s->id, collect()) as $r) {
                $codice = trim($r->codice);
                $quando = $r->created_at ?? Carbon::parse($r->data_rientro)->setTime(12, 0);

                $righe[] = [
                    'attrezzatura_id' => $this->pezzo($codice),
                    'rientrata_at' => $quando,
                    'esito' => $esiti->get($s->id.'|'.$codice, Esito::Integro),
                    'note' => $this->testo($r->note),
                    'created_at' => $noleggio->created_at,
                    'updated_at' => $quando,
                ];
            }

            foreach ($fuori->get($s->id, collect()) as $f) {
                $righe[] = [
                    'attrezzatura_id' => $this->pezzo(trim($f->codice_attrezzatura)),
                    'rientrata_at' => null,
                    'esito' => null,
                    'note' => null,
                    'created_at' => $f->created_at ?? $noleggio->created_at,
                    'updated_at' => $f->updated_at ?? $noleggio->created_at,
                ];
            }

            if ($righe) {
                $noleggio->righe()->createMany($righe);
                $conRighe++;
            }
        }

        $this->line("Noleggi: {$n} ({$conRighe} con le righe scritte adesso)");

        if ($ricreati = count(array_unique($this->pezzi)) - $pezziPrima) {
            $this->warn("  {$ricreati} codici dei noleggi non erano più in inventario: ricreati come pezzi fuori servizio.");
        }
    }

    private function segnalazioni(): void
    {
        $n = 0;

        foreach ($this->storico->table('stato_attrezzature')->orderBy('id')->cursor() as $s) {
            Segnalazione::updateOrCreate(['storico_id' => $s->id], [
                'attrezzatura_id' => $this->pezzo(trim($s->codice)),
                'noleggio_id' => $this->noleggi[$s->id_sessione] ?? null,
                'cliente_id' => $this->clienti[$s->id_anagrafica] ?? null,
                'esito' => Esito::daCausaleStorica($s->causale),
                'note' => $this->testo($s->note),
                'avvenuta_at' => $s->created_at ?? $s->data_evento,
            ]);
            $n++;
        }

        $this->line("Segnalazioni: {$n}");
    }

    /**
     * Nel vecchio pannello «stato = 1» chiudeva il noleggio, ma non sempre:
     * qui conta che non resti nessun pezzo fuori. La data è l'ultimo rientro.
     */
    private function chiusure(): void
    {
        $chiusi = 0;

        Noleggio::whereNotNull('storico_id')->withMax('righe', 'rientrata_at')->each(function (Noleggio $noleggio) use (&$chiusi) {
            $aperto = $noleggio->righeAperte()->exists();

            $campi = ['chiuso_at' => $aperto ? null : ($noleggio->righe_max_rientrata_at ?? $noleggio->data_rientro)];

            // Il vecchio pannello non segnava mai il pagamento: su un noleggio già
            // chiuso là, il promemoria è vecchio e si spegne (la nota resta).
            if (! $aperto && isset($this->nuovi[$noleggio->id])) {
                $campi['da_pagare'] = false;
            }

            $noleggio->updateQuietly($campi);

            $chiusi += $aperto ? 0 : 1;
        });

        $this->line("Noleggi chiusi: {$chiusi}, aperti: ".Noleggio::aperti()->count());
    }

    /** L'id del pezzo per un codice; se l'inventario non lo ha più, lo si ricrea fuori servizio. */
    private function pezzo(string $codice): int
    {
        return $this->pezzi[$codice] ??= Attrezzatura::firstOrCreate(['codice' => $codice], [
            'tipologia' => Tipologia::Altro,
            'stato' => StatoAttrezzatura::FuoriServizio,
            'note' => 'Ricreato dall\'importazione: il codice compariva nei noleggi del vecchio pannello ma non più nel suo inventario.',
        ])->id;
    }

    private function testo(?string $valore): ?string
    {
        $valore = trim((string) $valore);

        return $valore === '' ? null : $valore;
    }

    /** Il vecchio pannello scriveva 0 quando il prezzo non c'era. */
    private function prezzo(mixed $valore): ?string
    {
        return is_numeric($valore) && (float) $valore > 0 ? number_format((float) $valore, 2, '.', '') : null;
    }

    private function data(?string $valore): ?string
    {
        if (! $this->testo($valore) || str_starts_with($valore, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($valore)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
