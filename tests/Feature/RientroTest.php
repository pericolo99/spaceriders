<?php

namespace Tests\Feature;

use App\Enums\Esito;
use App\Enums\StatoAttrezzatura;
use App\Livewire\RientroOperazione;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Models\Noleggio;
use App\Models\User;
use App\Noleggi\Banco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RientroTest extends TestCase
{
    use RefreshDatabase;

    private Noleggio $noleggio;

    /** @var list<Attrezzatura> */
    private array $pezzi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->pezzi = Attrezzatura::factory()->count(3)->create()->all();
        $this->noleggio = app(Banco::class)->consegna(
            Cliente::factory()->create(),
            array_map(fn ($p) => $p->id, $this->pezzi),
            today()->subDays(3),
            today()->subDay(),
        );
    }

    private function riga(Attrezzatura $pezzo): int
    {
        return $this->noleggio->righe()->where('attrezzatura_id', $pezzo->id)->value('id');
    }

    public function test_esiti_diversi_per_pezzo_e_i_non_scelti_restano_fuori(): void
    {
        [$sci, $scarpone, $casco] = $this->pezzi;

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('scegli', $this->riga($sci), Esito::Integro->value)
            ->call('scegli', $this->riga($scarpone), Esito::Danneggiato->value)
            ->set('note.'.$this->riga($scarpone), 'Gancio rotto')
            ->call('registra')
            ->assertSet('aperto', false)
            ->assertDispatched('operazione-completata');

        $this->assertTrue($this->noleggio->refresh()->isAperto());
        $this->assertTrue($sci->refresh()->isDisponibile());
        $this->assertSame(StatoAttrezzatura::FuoriServizio, $scarpone->refresh()->stato);
        $this->assertSame('Gancio rotto', $scarpone->segnalazioni()->sole()->note);
        $this->assertTrue($casco->refresh()->isFuori());
    }

    public function test_tutti_a_posto_chiude_il_noleggio(): void
    {
        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('tuttiAPosto')
            ->assertCount('esiti', 3)
            ->call('registra');

        $this->assertFalse($this->noleggio->refresh()->isAperto());
    }

    public function test_ritoccare_una_scelta_la_toglie_e_la_lettura_segna_a_posto(): void
    {
        [$sci, $scarpone] = $this->pezzi;
        $estraneo = Attrezzatura::factory()->create();

        $finestra = Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('scegli', $this->riga($sci), Esito::DaControllare->value)
            ->call('scegli', $this->riga($sci), Esito::DaControllare->value)
            ->assertSet('esiti', [])
            ->call('leggi', $scarpone->codice)
            ->assertSet('esiti', [$this->riga($scarpone) => Esito::Integro->value])
            ->call('leggi', $estraneo->codice)
            ->assertCount('esiti', 1);

        $this->assertTrue($finestra->instance()->haDati());
    }

    public function test_non_si_scelgono_righe_di_altri_noleggi(): void
    {
        $altro = app(Banco::class)->consegna(Cliente::factory()->create(), [Attrezzatura::factory()->create()->id], today(), today());

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('scegli', $altro->righe()->value('id'), Esito::Integro->value)
            ->assertSet('esiti', []);
    }

    public function test_le_foto_del_danno_si_salvano_ridotte_con_la_segnalazione(): void
    {
        Storage::fake('public');
        [$sci] = $this->pezzi;
        $riga = $this->riga($sci);

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('scegli', $riga, Esito::Danneggiato->value)
            ->set("nuove.{$riga}", [UploadedFile::fake()->image('soletta.jpg', 4000, 3000)])
            ->set("nuove.{$riga}", [UploadedFile::fake()->image('lamina.jpg', 1200, 900), UploadedFile::fake()->image('attacco.jpg', 800, 600)])
            ->assertCount("foto.{$riga}", 3)
            ->call('togliFoto', $riga, 2)
            ->assertCount("foto.{$riga}", 2)
            ->call('registra')
            ->assertHasNoErrors();

        $foto = $sci->segnalazioni()->sole()->foto;
        $this->assertCount(2, $foto);
        foreach ($foto as $percorso) {
            Storage::disk('public')->assertExists($percorso);
        }
        [$larghezza, $altezza] = getimagesizefromstring(Storage::disk('public')->get($foto[0]));
        $this->assertSame([1600, 1200], [$larghezza, $altezza], 'la foto da 4000 px si riduce a 1600');
    }

    public function test_al_massimo_sei_foto_e_solo_immagini(): void
    {
        [$sci] = $this->pezzi;
        $riga = $this->riga($sci);
        $immagini = fn (int $n) => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg", 100, 100), range(1, $n));

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('scegli', $riga, Esito::DaControllare->value)
            ->set("nuove.{$riga}", $immagini(4))
            ->set("nuove.{$riga}", $immagini(4))
            ->assertCount("foto.{$riga}", RientroOperazione::FOTO_PER_PEZZO)
            ->set("nuove.{$riga}", [UploadedFile::fake()->create('contratto.pdf', 50, 'application/pdf')])
            ->assertHasErrors(["nuove.{$riga}.0"]);
    }

    public function test_un_pezzo_che_non_torna_non_porta_foto(): void
    {
        Storage::fake('public');
        [$sci] = $this->pezzi;
        $riga = $this->riga($sci);

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('scegli', $riga, Esito::Danneggiato->value)
            ->set("nuove.{$riga}", [UploadedFile::fake()->image('a.jpg', 100, 100)])
            ->call('scegli', $riga, Esito::NonRiconsegnato->value)
            ->assertSet('foto', [])
            ->call('registra');

        $this->assertNull($sci->segnalazioni()->sole()->foto);
    }

    public function test_leggere_un_pezzo_apre_tutto_il_suo_noleggio_con_il_pezzo_smarcato(): void
    {
        [$sci, $scarpone] = $this->pezzi;

        $finestra = Livewire::test(RientroOperazione::class)
            ->call('apri')
            ->assertSet('noleggioId', null)
            ->call('leggi', $sci->codice)
            ->assertSet('noleggioId', $this->noleggio->id)
            ->assertSet('esiti', [$this->riga($sci) => Esito::Integro->value])
            ->assertSee($scarpone->nome);

        $this->assertCount(3, $finestra->instance()->righeAperte());
    }

    public function test_un_pezzo_di_un_altro_noleggio_cambia_cliente_solo_se_non_si_e_smarcato_nulla(): void
    {
        [$sci] = $this->pezzi;
        $altroPezzo = Attrezzatura::factory()->create();
        $altro = app(Banco::class)->consegna(Cliente::factory()->create(['cognome' => 'Verdi', 'nome' => 'Ugo']), [$altroPezzo->id], today(), today());

        // Con qualcosa di smarcato: avviso, il noleggio resta quello.
        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('leggi', $sci->codice)
            ->call('leggi', $altroPezzo->codice)
            ->assertSet('noleggioId', $this->noleggio->id)
            ->assertNotified('È di un altro noleggio');

        // Senza nulla di smarcato: si passa all'altro noleggio.
        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('leggi', $altroPezzo->codice)
            ->assertSet('noleggioId', $altro->id);
    }

    public function test_un_pezzo_non_a_noleggio_avvisa(): void
    {
        Livewire::test(RientroOperazione::class)
            ->call('apri')
            ->call('leggi', Attrezzatura::factory()->create()->codice)
            ->assertSet('noleggioId', null)
            ->assertNotified('Pezzo non a noleggio');
    }

    public function test_il_rientro_puo_segnare_pagato(): void
    {
        $this->noleggio->update(['da_pagare' => true, 'note_pagamento' => 'manca il saldo']);
        [$sci] = $this->pezzi;

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->assertSee('Da pagare')
            ->assertSee('manca il saldo')
            ->set('pagato', true)
            ->call('scegli', $this->riga($sci), Esito::Integro->value)
            ->call('registra');

        $this->assertFalse($this->noleggio->refresh()->da_pagare);
        $this->assertNotNull($this->noleggio->pagato_at);
    }

    public function test_senza_spunta_il_promemoria_resta(): void
    {
        $this->noleggio->update(['da_pagare' => true]);
        [$sci] = $this->pezzi;

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->call('scegli', $this->riga($sci), Esito::Integro->value)
            ->call('registra');

        $this->assertTrue($this->noleggio->refresh()->da_pagare);
    }

    public function test_una_nota_sul_pagamento_senza_promemoria_si_legge(): void
    {
        $this->noleggio->update(['note_pagamento' => 'pagato con bonifico']);

        Livewire::test(RientroOperazione::class)
            ->call('apri', $this->noleggio->id)
            ->assertSee('Nota sul pagamento')
            ->assertSee('pagato con bonifico')
            ->assertDontSee('Spunta quando il cliente paga');
    }
}
