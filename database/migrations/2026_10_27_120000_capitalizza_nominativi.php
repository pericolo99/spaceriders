<?php

use App\Support\Nominativo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * I nominativi già importati, con l'iniziale maiuscola. Da qui in avanti ci
 * pensa il modello Cliente a ogni salvataggio; per gli utenti, il loro modello.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('clienti')->select(['id', 'nome', 'cognome'])->orderBy('id')->each(function ($c) {
            DB::table('clienti')->where('id', $c->id)->update([
                'nome' => Nominativo::capitalizza($c->nome),
                'cognome' => Nominativo::capitalizza($c->cognome),
            ]);
        });

        DB::table('users')->select(['id', 'name'])->orderBy('id')->each(function ($u) {
            DB::table('users')->where('id', $u->id)->update(['name' => Nominativo::capitalizza($u->name) ?? $u->name]);
        });
    }

    public function down(): void
    {
        // Le maiuscole originali non si recuperano: si rifà l'importazione.
    }
};
