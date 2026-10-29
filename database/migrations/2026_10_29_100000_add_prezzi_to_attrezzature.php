<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quanto è costato il pezzo e a quanto si vende: come nel vecchio inventario. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attrezzature', function (Blueprint $table) {
            $table->decimal('prezzo_acquisto', 10, 2)->nullable()->after('data_acquisto');
            $table->decimal('prezzo_vendita', 10, 2)->nullable()->after('prezzo_acquisto');
        });
    }

    public function down(): void
    {
        Schema::table('attrezzature', function (Blueprint $table) {
            $table->dropColumn(['prezzo_acquisto', 'prezzo_vendita']);
        });
    }
};
