<?php

namespace App\Noleggi;

use App\Enums\Esito;
use App\Enums\StatoAttrezzatura;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Models\Noleggio;
use App\Models\RigaNoleggio;
use App\Models\Segnalazione;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Le tre cose che si fanno al banco: consegnare, far rientrare, segnalare un
 * pezzo che non torna. Le pagine Filament raccolgono i dati, le regole stanno qui.
 */
class Banco
{
    /**
     * Il pezzo che si può consegnare per questo codice, o il motivo per cui no.
     *
     * @throws BancoException
     */
    public function pezzoDaConsegnare(string $codice): Attrezzatura
    {
        $pezzo = Attrezzatura::perCodice($codice)
            ?? throw new BancoException('Codice non trovato', "Nessun pezzo con il codice «{$codice}».");

        if ($pezzo->stato !== StatoAttrezzatura::Attiva) {
            throw new BancoException('Pezzo non consegnabile', "{$pezzo->nome} è {$this->minuscolo($pezzo->stato->getLabel())}.");
        }

        if ($riga = $pezzo->rigaAperta()->with('noleggio.cliente')->first()) {
            throw new BancoException('Pezzo già fuori', "{$pezzo->nome} è già fuori con {$riga->noleggio->cliente->nominativo}.");
        }

        return $pezzo;
    }

    /**
     * @param  list<int>  $attrezzature  id dei pezzi già controllati con pezzoDaConsegnare()
     * @param  array{sci?: int, snow?: int, scarponi?: int}  $sacche
     *
     * @throws BancoException
     */
    public function consegna(
        Cliente $cliente,
        array $attrezzature,
        CarbonInterface $dataConsegna,
        CarbonInterface $dataRientro,
        int $giorniExtra = 0,
        array $sacche = [],
        ?string $note = null,
        ?User $operatore = null,
        bool $daPagare = false,
        ?string $notePagamento = null,
    ): Noleggio {
        if ($attrezzature === []) {
            throw new BancoException('Nessun pezzo', 'Leggi almeno un pezzo da consegnare.');
        }

        if ($dataRientro->lt($dataConsegna->copy()->startOfDay())) {
            throw new BancoException('Date non valide', 'Il rientro non può essere prima della consegna.');
        }

        return DB::transaction(function () use ($cliente, $attrezzature, $dataConsegna, $dataRientro, $giorniExtra, $sacche, $note, $operatore, $daPagare, $notePagamento) {
            // Si ricontrolla dentro la transazione, con i pezzi bloccati: due
            // postazioni potrebbero aver letto lo stesso codice nello stesso momento.
            $pezzi = Attrezzatura::whereKey($attrezzature)->lockForUpdate()->get();

            foreach ($pezzi as $pezzo) {
                $this->pezzoDaConsegnare($pezzo->codice);
            }

            $noleggio = $cliente->noleggi()->create([
                'user_id' => $operatore?->id,
                'data_consegna' => $dataConsegna->toDateString(),
                'data_rientro' => $dataRientro->toDateString(),
                'giorni_extra' => $giorniExtra,
                'sacche_sci' => (int) ($sacche['sci'] ?? 0),
                'sacche_snow' => (int) ($sacche['snow'] ?? 0),
                'sacche_scarponi' => (int) ($sacche['scarponi'] ?? 0),
                'note' => $note,
                'da_pagare' => $daPagare,
                'note_pagamento' => $daPagare ? $notePagamento : null,
            ]);

            $noleggio->righe()->createMany(
                $pezzi->map(fn (Attrezzatura $p) => ['attrezzatura_id' => $p->id])->all()
            );

            return $noleggio;
        });
    }

    /**
     * La riga aperta del pezzo con questo codice: chi ce l'ha e da quando.
     *
     * @throws BancoException
     */
    public function rigaDaRientrare(string $codice): RigaNoleggio
    {
        $pezzo = Attrezzatura::perCodice($codice)
            ?? throw new BancoException('Codice non trovato', "Nessun pezzo con il codice «{$codice}».");

        return $pezzo->rigaAperta()->with(['noleggio.cliente', 'attrezzatura'])->first()
            ?? throw new BancoException('Pezzo non a noleggio', "{$pezzo->nome} non risulta fuori a noleggio.");
    }

    /**
     * Chiude il giro di un pezzo. Vale anche per i pezzi che non tornano
     * (smarrito, non riconsegnato): la riga si chiude con quell'esito e il
     * pezzo esce dal servizio.
     *
     * @param  list<string>  $foto  percorsi sul disco public
     */
    public function rientro(
        RigaNoleggio $riga,
        Esito $esito = Esito::Integro,
        ?string $note = null,
        array $foto = [],
        ?User $operatore = null,
    ): RigaNoleggio {
        if (! $riga->isAperta()) {
            throw new BancoException('Già rientrato', "{$riga->attrezzatura->nome} è già rientrato.");
        }

        return DB::transaction(function () use ($riga, $esito, $note, $foto, $operatore) {
            $riga->update([
                'rientrata_at' => now(),
                'esito' => $esito,
                'rientro_user_id' => $operatore?->id,
                'note' => $note,
            ]);

            $riga->attrezzatura->update(['stato' => $esito->statoAttrezzatura()]);

            if ($esito !== Esito::Integro) {
                Segnalazione::create([
                    'attrezzatura_id' => $riga->attrezzatura_id,
                    'noleggio_id' => $riga->noleggio_id,
                    'cliente_id' => $riga->noleggio->cliente_id,
                    'user_id' => $operatore?->id,
                    'esito' => $esito,
                    'note' => $note,
                    'foto' => $foto ?: null,
                    'avvenuta_at' => now(),
                ]);
            }

            $this->chiudiSeCompleto($riga->noleggio);

            return $riga;
        });
    }

    /** Il cliente ha pagato: il promemoria «Da pagare» sparisce, la nota resta. */
    public function segnaPagato(Noleggio $noleggio): void
    {
        if ($noleggio->da_pagare) {
            $noleggio->update(['da_pagare' => false, 'pagato_at' => now()]);
        }
    }

    /** Rimette in servizio un pezzo da controllare o fuori servizio. */
    public function rimettiInServizio(Attrezzatura $pezzo): void
    {
        DB::transaction(function () use ($pezzo) {
            $pezzo->update(['stato' => StatoAttrezzatura::Attiva]);
            $pezzo->segnalazioni()->whereNull('risolta_at')->update(['risolta_at' => now()]);
        });
    }

    /** Un noleggio si chiude quando non gli resta nessun pezzo fuori. */
    public function chiudiSeCompleto(Noleggio $noleggio): void
    {
        if ($noleggio->isAperto() && ! $noleggio->righeAperte()->exists()) {
            $noleggio->update(['chiuso_at' => now()]);
        }
    }

    private function minuscolo(string $testo): string
    {
        return mb_strtolower($testo);
    }
}
