<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La foto del prodotto: di solito la stessa per tutti i pezzi di un modello. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attrezzature', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('descrizione');
        });
    }

    public function down(): void
    {
        Schema::table('attrezzature', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
