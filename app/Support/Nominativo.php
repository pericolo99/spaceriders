<?php

namespace App\Support;

/**
 * Nomi e cognomi con l'iniziale maiuscola, comunque siano stati scritti:
 * «TERENZONI» → «Terenzoni», «dell'amico» → «Dell'Amico», «de luca» → «De Luca».
 */
class Nominativo
{
    public static function capitalizza(?string $testo): ?string
    {
        $testo = preg_replace('/\s+/u', ' ', trim((string) $testo));

        if ($testo === '') {
            return null;
        }

        $testo = mb_convert_case($testo, MB_CASE_TITLE, 'UTF-8');

        // MB_CASE_TITLE non riparte dopo l'apostrofo: D'angelo, Dell'amico.
        return preg_replace_callback(
            "/(['’])(\p{Ll})/u",
            fn (array $m): string => $m[1].mb_strtoupper($m[2], 'UTF-8'),
            $testo,
        );
    }
}
