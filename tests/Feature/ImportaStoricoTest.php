<?php

namespace Tests\Feature;

use App\Enums\Esito;
use App\Enums\StatoAttrezzatura;
use App\Enums\Tipologia;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Models\Noleggio;
use App\Noleggi\Banco;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Il vecchio pannello ridotto all'osso, in SQLite: solo le colonne che l'importazione legge. */
class ImportaStoricoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.storico' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('storico');
        $s = Schema::connection('storico');

        $s->create('users', function (Blueprint $t) {
            $t->integer('id');
            $t->string('email');
            $t->string('password');
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        $s->create('anagrafica', function (Blueprint $t) {
            $t->integer('id');
            foreach (['nome', 'cognome', 'codice_fiscale', 'luogo_nascita', 'indirizzo', 'citta', 'cap', 'localita', 'contatto_telefono', 'contatto_email', 'descrizione', 'registrazione', 'data_nascita', 'data_creazione', 'created_at'] as $c) {
                $t->string($c)->nullable();
            }
        });
        $s->create('attrezzature', function (Blueprint $t) {
            $t->integer('id');
            foreach (['codice', 'codice_gestionale', 'tipologia', 'marca', 'modello', 'misura', 'descrizione', 'stato', 'data_acquisto', 'note', 'created_at'] as $c) {
                $t->string($c)->nullable();
            }
        });
        $s->create('sessione_noleggio', function (Blueprint $t) {
            $t->integer('id');
            foreach (['id_anagrafica', 'data_consegna', 'data_rientro', 'extra_gg', 'sacche_sci', 'sacche_snow', 'sacche_scarponi', 'note', 'stato', 'created_at', 'pagamento_consegna', 'note_pagamento'] as $c) {
                $t->string($c)->nullable();
            }
        });
        $s->create('sessione_noleggio_attrezzature', function (Blueprint $t) {
            $t->integer('id');
            foreach (['id_prenotazione', 'codice_attrezzatura', 'created_at', 'updated_at'] as $c) {
                $t->string($c)->nullable();
            }
        });
        $s->create('sessione_noleggio_rientro', function (Blueprint $t) {
            $t->integer('id');
            foreach (['id_sessione', 'codice', 'data_rientro', 'note', 'created_at'] as $c) {
                $t->string($c)->nullable();
            }
        });
        $s->create('stato_attrezzature', function (Blueprint $t) {
            $t->integer('id');
            foreach (['id_sessione', 'id_anagrafica', 'codice', 'data_evento', 'causale', 'note', 'created_at'] as $c) {
                $t->string($c)->nullable();
            }
        });

        $db = DB::connection('storico');
        $db->table('users')->insert(['id' => 1, 'email' => 'Banco@Example.it', 'password' => Hash::make('segreta'), 'first_name' => 'Anna', 'last_name' => 'Neri']);
        $db->table('anagrafica')->insert(['id' => 10, 'nome' => 'Luca', 'cognome' => 'Conti', 'contatto_telefono' => ' 333 ', 'registrazione' => 'Portale']);
        $db->table('attrezzature')->insert([
            ['id' => 1, 'codice' => '61978001', 'tipologia' => 'SKI', 'marca' => 'HEAD', 'modello' => 'KORE', 'misura' => '170', 'stato' => null, 'codice_gestionale' => ''],
            ['id' => 2, 'codice' => '61978002', 'tipologia' => 'SSK', 'marca' => 'LANGE', 'modello' => 'RX', 'misura' => '27', 'stato' => '-1', 'codice_gestionale' => null],
            ['id' => 3, 'codice' => '61978003', 'tipologia' => 'XYZ', 'marca' => null, 'modello' => null, 'misura' => null, 'stato' => '', 'codice_gestionale' => null],
        ]);
        // Noleggio 100: chiuso, uno scarpone rientrato rotto e un pezzo che non è più in inventario.
        // Noleggio 101: segnato chiuso (stato 1) ma con lo sci ancora fuori.
        $db->table('sessione_noleggio')->insert([
            ['id' => 100, 'id_anagrafica' => 10, 'data_consegna' => '2025-12-20', 'data_rientro' => '2025-12-27', 'extra_gg' => '0', 'sacche_sci' => '1', 'sacche_snow' => '', 'sacche_scarponi' => null, 'stato' => '1', 'pagamento_consegna' => 'on', 'note_pagamento' => 'paga il papà'],
            ['id' => 101, 'id_anagrafica' => 10, 'data_consegna' => '2026-01-03', 'data_rientro' => '2026-01-05', 'extra_gg' => '2', 'sacche_sci' => null, 'sacche_snow' => null, 'sacche_scarponi' => null, 'stato' => '1', 'pagamento_consegna' => 'on', 'note_pagamento' => null],
        ]);
        $db->table('sessione_noleggio_rientro')->insert([
            ['id' => 1, 'id_sessione' => 100, 'codice' => '61978002', 'data_rientro' => '2025-12-27'],
            ['id' => 2, 'id_sessione' => 100, 'codice' => '28170001', 'data_rientro' => '2025-12-27'],
        ]);
        $db->table('sessione_noleggio_attrezzature')->insert(['id' => 1, 'id_prenotazione' => 101, 'codice_attrezzatura' => '61978001']);
        $db->table('stato_attrezzature')->insert(['id' => 5, 'id_sessione' => 100, 'id_anagrafica' => 10, 'codice' => '61978002', 'data_evento' => '2025-12-27', 'causale' => 'RTT', 'note' => 'gancio rotto']);
    }

    public function test_importa_e_si_puo_rilanciare(): void
    {
        $this->artisan('kaukau:importa-storico')->assertSuccessful();
        $this->artisan('kaukau:importa-storico')->assertSuccessful();

        // Utente: stessa password, email normalizzata.
        $this->assertTrue(auth()->attempt(['email' => 'banco@example.it', 'password' => 'segreta']));

        $cliente = Cliente::sole();
        $this->assertSame('Conti Luca', $cliente->nominativo);
        $this->assertSame('333', $cliente->telefono);
        $this->assertSame('portale', $cliente->origine);

        $this->assertSame(Tipologia::Altro, Attrezzatura::where('codice', '61978003')->sole()->tipologia);
        $this->assertSame(StatoAttrezzatura::FuoriServizio, Attrezzatura::where('codice', '61978002')->sole()->stato);
        $ricreato = Attrezzatura::where('codice', '28170001')->sole();
        $this->assertSame(StatoAttrezzatura::FuoriServizio, $ricreato->stato);

        $chiuso = Noleggio::where('storico_id', 100)->sole();
        $this->assertFalse($chiuso->isAperto());
        $this->assertSame(1, $chiuso->sacche_sci);
        $this->assertSame(2, $chiuso->righe()->count());
        $this->assertSame(Esito::Danneggiato, $chiuso->righe()->whereHas('attrezzatura', fn ($q) => $q->where('codice', '61978002'))->sole()->esito);
        $this->assertSame('gancio rotto', $chiuso->segnalazioni()->sole()->note);
        // Chiuso nel vecchio: il promemoria è vecchio e si spegne, la nota resta.
        $this->assertFalse($chiuso->da_pagare);
        $this->assertSame('paga il papà', $chiuso->note_pagamento);

        // Lo sci è ancora fuori: il noleggio resta aperto anche se il vecchio pannello lo dava chiuso.
        $aperto = Noleggio::where('storico_id', 101)->sole();
        $this->assertTrue($aperto->isAperto());
        $this->assertTrue(Attrezzatura::where('codice', '61978001')->sole()->isFuori());
        $this->assertTrue($aperto->da_pagare);

        // Pagato nel pannello nuovo: una nuova importazione non riaccende il promemoria.
        app(Banco::class)->segnaPagato($aperto);
        $this->artisan('kaukau:importa-storico')->assertSuccessful();
        $this->assertFalse($aperto->refresh()->da_pagare);

        $this->assertSame(2, Noleggio::count());
        $this->assertSame(3, DB::table('noleggio_righe')->count());
    }
}
