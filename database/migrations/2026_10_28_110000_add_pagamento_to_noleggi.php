<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noleggi', function (Blueprint $table) {
            // Un promemoria per il banco, non una contabilità: niente importi.
            // Nel vecchio pannello era la casella «Pagamento alla consegna».
            $table->boolean('da_pagare')->default(false)->after('note');
            $table->string('note_pagamento')->nullable()->after('da_pagare');
            $table->timestamp('pagato_at')->nullable()->after('note_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('noleggi', function (Blueprint $table) {
            $table->dropColumn(['da_pagare', 'note_pagamento', 'pagato_at']);
        });
    }
};
