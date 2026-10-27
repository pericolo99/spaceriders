<?php

namespace App\Livewire\Concerns;

use App\Models\Attrezzatura;

/**
 * I suggerimenti sotto il campo del codice: mentre si digita, i pezzi che
 * corrispondono, con immagine e dettagli. Chi usa il trait decide quali pezzi
 * proporre e quali si possono scegliere.
 */
trait SuggerisceAttrezzatura
{
    public const SUGGERIMENTI = 8;

    /**
     * @return list<array{codice: string, nome: string, dettaglio: string, immagine: string, sfondo: string, stato: string, colore: string, scegli: bool}>
     */
    public function getSuggerimenti(): array
    {
        $testo = trim($this->codice);

        return mb_strlen($testo) < 2 ? [] : $this->suggerimenti($testo);
    }

    /** @return list<array{codice: string, nome: string, dettaglio: string, immagine: string, sfondo: string, stato: string, colore: string, scegli: bool}> */
    abstract protected function suggerimenti(string $testo): array;

    /** @return array{codice: string, nome: string, dettaglio: string, immagine: string, sfondo: string} */
    protected function descrivi(Attrezzatura $pezzo): array
    {
        return [
            'codice' => $pezzo->codice,
            'nome' => trim("{$pezzo->marca} {$pezzo->modello}") ?: $pezzo->tipologia->getLabel(),
            'dettaglio' => implode(' · ', array_filter([$pezzo->tipologia->getLabel(), $pezzo->misura, $pezzo->codice])),
            'immagine' => $pezzo->immagine(),
            'sfondo' => $pezzo->sfondoImmagine(),
        ];
    }
}
