<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il dominio del noleggio: chi noleggia, che cosa, quando lo porta via e
 * quando lo riporta.
 *
 * Nel vecchio pannello il pezzo in uscita stava in una tabella ponte che si
 * svuotava al rientro, e il rientro finiva in un'altra tabella: per sapere
 * dov'era un pezzo bisognava guardarne due. Qui ogni pezzo consegnato è una
 * riga di noleggio che resta, e il rientro la chiude.
 *
 * `storico_id` tiene l'id del vecchio pannello: rende l'importazione
 * ripetibile senza duplicati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('storico_id')->nullable()->unique()->after('id');
        });

        Schema::create('clienti', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('storico_id')->nullable()->unique();
            $table->string('nome')->nullable();
            $table->string('cognome')->nullable();
            $table->string('codice_fiscale', 32)->nullable()->index();
            $table->date('data_nascita')->nullable();
            $table->string('luogo_nascita')->nullable();
            $table->string('indirizzo')->nullable();
            $table->string('citta')->nullable();
            $table->string('cap', 10)->nullable();
            $table->string('localita')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['cognome', 'nome']);
        });

        Schema::create('attrezzature', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('storico_id')->nullable()->unique();
            // Il codice a barre sull'etichetta: è quello che si scansiona.
            $table->string('codice')->unique();
            // Il codice del gestionale del fornitore, quando c'è: si accetta anche questo in scansione.
            $table->string('codice_gestionale')->nullable()->index();
            $table->string('tipologia', 8)->index();
            $table->string('marca')->nullable();
            $table->string('modello')->nullable();
            $table->string('misura')->nullable();
            $table->string('descrizione')->nullable();
            $table->string('stato', 20)->default('attiva')->index();
            $table->date('data_acquisto')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('noleggi', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('storico_id')->nullable()->unique();
            $table->foreignId('cliente_id')->constrained('clienti')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('data_consegna')->index();
            $table->date('data_rientro')->index();
            $table->unsignedSmallInteger('giorni_extra')->default(0);
            $table->unsignedTinyInteger('sacche_sci')->default(0);
            $table->unsignedTinyInteger('sacche_snow')->default(0);
            $table->unsignedTinyInteger('sacche_scarponi')->default(0);
            $table->text('note')->nullable();
            // Pieno quando tutti i pezzi sono rientrati (o segnalati).
            $table->timestamp('chiuso_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('noleggio_righe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('noleggio_id')->constrained('noleggi')->cascadeOnDelete();
            $table->foreignId('attrezzatura_id')->constrained('attrezzature')->restrictOnDelete();
            $table->timestamp('rientrata_at')->nullable()->index();
            $table->string('esito', 20)->nullable();
            $table->foreignId('rientro_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['attrezzatura_id', 'rientrata_at']);
        });

        // Tutto ciò che succede a un pezzo oltre al normale giro: rientrato
        // danneggiato o da controllare, smarrito, mai riconsegnato.
        Schema::create('segnalazioni', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('storico_id')->nullable()->unique();
            $table->foreignId('attrezzatura_id')->constrained('attrezzature')->cascadeOnDelete();
            $table->foreignId('noleggio_id')->nullable()->constrained('noleggi')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clienti')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('esito', 20)->index();
            $table->text('note')->nullable();
            $table->json('foto')->nullable();
            $table->timestamp('avvenuta_at');
            // Quando il pezzo è stato rimesso in servizio dopo la segnalazione.
            $table->timestamp('risolta_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segnalazioni');
        Schema::dropIfExists('noleggio_righe');
        Schema::dropIfExists('noleggi');
        Schema::dropIfExists('attrezzature');
        Schema::dropIfExists('clienti');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['storico_id']);
            $table->dropColumn('storico_id');
        });
    }
};
