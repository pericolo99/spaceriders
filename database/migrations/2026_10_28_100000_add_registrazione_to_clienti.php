<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            // Chi ha scritto la scheda: l'operatore al banco o il cliente dalla
            // pagina pubblica di registrazione (nel vecchio pannello «Portale»).
            $table->string('origine', 16)->default('banco')->after('note');
            // Quando ha accettato termini e trattamento dei dati, se si è registrato da sé.
            $table->timestamp('consenso_at')->nullable()->after('origine');
        });
    }

    public function down(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn(['origine', 'consenso_at']);
        });
    }
};
