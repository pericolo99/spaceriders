<?php

namespace Tests\Feature;

use App\Enums\Esito;
use App\Enums\StatoAttrezzatura;
use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
use App\Filament\Resources\Clienti\ClienteResource;
use App\Filament\Resources\Noleggi\NoleggioResource;
use App\Filament\Resources\Noleggi\Pages\ViewNoleggio;
use App\Filament\Resources\Noleggi\RelationManagers\RigheRelationManager;
use App\Filament\Resources\Segnalazioni\SegnalazioneResource;
use App\Filament\Resources\Utenti\UserResource;
use App\Livewire\ConsegnaOperazione;
use App\Livewire\NuovoClienteOperazione;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Models\Noleggio;
use App\Models\User;
use App\Noleggi\Banco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PagineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => 'Marco Rossi']));
    }

    private function noleggioConUnPezzo(): Noleggio
    {
        $sci = Attrezzatura::factory()->create();

        return app(Banco::class)->consegna(Cliente::factory()->create(), [$sci->id], today()->subDays(3), today()->subDay());
    }

    public function test_senza_login_si_va_al_login(): void
    {
        auth()->logout();

        $this->get('/')->assertRedirect('/login');
    }

    public function test_le_pagine_si_aprono(): void
    {
        $noleggio = $this->noleggioConUnPezzo();
        $pezzo = $noleggio->righe()->first()->attrezzatura;

        $this->get('/')->assertOk()->assertSee('Buongiorno, Marco')->assertSee($noleggio->cliente->nominativo);
        $this->get('/?apri=consegna')->assertOk()->assertSeeLivewire(ConsegnaOperazione::class);
        $this->get(NoleggioResource::getUrl('index'))->assertOk();
        $this->get(NoleggioResource::getUrl('view', ['record' => $noleggio]))->assertOk()->assertSee($noleggio->cliente->nominativo);
        $this->get(NoleggioResource::getUrl('edit', ['record' => $noleggio]))->assertOk();
        $this->get(AttrezzaturaResource::getUrl('index'))->assertOk();
        $this->get(AttrezzaturaResource::getUrl('create'))->assertOk();
        $this->get(AttrezzaturaResource::getUrl('edit', ['record' => $pezzo]))->assertOk();
        $this->get(ClienteResource::getUrl('index'))->assertOk();
        $this->get(ClienteResource::getUrl('edit', ['record' => $noleggio->cliente]))->assertOk();
        $this->get(SegnalazioneResource::getUrl('index'))->assertOk();
        $this->get(UserResource::getUrl('index'))->assertOk();
    }

    public function test_consegna_dalla_pagina(): void
    {
        $cliente = Cliente::factory()->create();
        [$sci, $scarponi] = Attrezzatura::factory()->count(2)->create();

        Livewire::test(ConsegnaOperazione::class)
            ->call('apri')
            ->assertSet('aperto', true)
            ->set('dati.cliente_id', $cliente->id)
            ->call('aggiungi', $sci->codice)
            ->set('codice', $scarponi->codice)
            ->call('aggiungi')
            ->call('aggiungi', $sci->codice) // la seconda lettura dello stesso pezzo non lo raddoppia
            ->assertSet('pezzi', [$sci->id, $scarponi->id])
            ->call('conferma')
            ->assertHasNoErrors()
            ->assertSet('aperto', false)
            ->assertSet('pezzi', [])
            ->assertDispatched('operazione-completata');

        $noleggio = $cliente->noleggi()->sole();
        $this->assertSame(2, $noleggio->righe()->count());
        $this->assertSame(auth()->id(), $noleggio->user_id);
    }

    public function test_consegna_senza_cliente_non_parte(): void
    {
        $sci = Attrezzatura::factory()->create();

        $finestra = Livewire::test(ConsegnaOperazione::class)
            ->call('apri')
            ->call('aggiungi', $sci->codice)
            ->call('conferma')
            ->assertHasErrors(['dati.cliente_id']);

        // In italiano e con il nome del campo, mai la chiave grezza «validation.required».
        $this->assertSame('Il campo cliente è obbligatorio.', $finestra->errors()->first('dati.cliente_id'));

        $this->assertSame(0, Noleggio::count());
    }

    public function test_dalla_scheda_del_noleggio_si_segnala_un_pezzo_che_non_torna(): void
    {
        $noleggio = $this->noleggioConUnPezzo();
        $riga = $noleggio->righe()->sole();

        Livewire::test(RigheRelationManager::class, ['ownerRecord' => $noleggio, 'pageClass' => ViewNoleggio::class])
            ->assertSee($riga->attrezzatura->codice)
            ->callTableAction('segnala', $riga, data: ['esito' => Esito::Smarrito->value, 'note' => 'perso in pista'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(Esito::Smarrito, $riga->refresh()->esito);
        $this->assertSame(StatoAttrezzatura::FuoriServizio, $riga->attrezzatura->refresh()->stato);
        $this->assertFalse($noleggio->refresh()->isAperto());
    }

    public function test_la_finestra_chiede_conferma_solo_se_ce_qualcosa_da_perdere(): void
    {
        $sci = Attrezzatura::factory()->create();

        $finestra = Livewire::test(ConsegnaOperazione::class)->call('apri');
        $this->assertFalse($finestra->instance()->haDati());

        $finestra->call('aggiungi', $sci->codice);
        $this->assertTrue($finestra->instance()->haDati());

        // «Esci senza salvare»: si chiude e non resta niente per la prossima volta.
        $finestra->call('chiudi')->assertSet('aperto', false)->assertSet('pezzi', []);
        $this->assertSame(0, Noleggio::count());
    }

    public function test_la_consegna_aperta_da_un_cliente_parte_con_quel_cliente(): void
    {
        $cliente = Cliente::factory()->create();

        $finestra = Livewire::test(ConsegnaOperazione::class)->call('apri', $cliente->id)
            ->assertSet('dati.cliente_id', $cliente->id);

        $this->assertFalse($finestra->instance()->haDati());
    }

    public function test_nuovo_cliente_e_poi_consegna(): void
    {
        Livewire::test(NuovoClienteOperazione::class)
            ->call('apri')
            ->set('dati.nome', 'giulia')
            ->set('dati.cognome', "DELL'ORTO")
            ->call('salva', consegna: true)
            ->assertHasNoErrors()
            ->assertSet('aperto', false)
            ->assertDispatched('apri-consegna', cliente: Cliente::sole()->id);

        $this->assertSame("Dell'Orto Giulia", Cliente::sole()->nominativo);
    }
}
