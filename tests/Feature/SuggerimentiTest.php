<?php

namespace Tests\Feature;

use App\Enums\StatoAttrezzatura;
use App\Enums\Tipologia;
use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
use App\Livewire\ConsegnaOperazione;
use App\Livewire\RientroOperazione;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Models\User;
use App\Noleggi\Banco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuggerimentiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_la_ricerca_combina_le_parole(): void
    {
        $giusto = Attrezzatura::factory()->create(['marca' => 'ROSSIGNOL', 'modello' => 'EXPERIENCE', 'misura' => '170']);
        Attrezzatura::factory()->create(['marca' => 'ROSSIGNOL', 'modello' => 'EXPERIENCE', 'misura' => '162']);
        Attrezzatura::factory()->create(['marca' => 'HEAD', 'modello' => 'KORE', 'misura' => '170']);

        $this->assertSame([$giusto->id], Attrezzatura::cerca('rossignol 170')->pluck('id')->all());
        $this->assertSame(0, Attrezzatura::cerca('100%')->count());

        $scarpone = Attrezzatura::factory()->create(['marca' => 'HEAD', 'modello' => 'EDGE', 'tipologia' => Tipologia::ScarponiSci]);
        $this->assertSame([$scarpone->id], Attrezzatura::cerca('scarponi head')->pluck('id')->all());
        $this->assertContains(Attrezzatura::where('modello', 'KORE')->value('id'), Attrezzatura::cerca('sci head')->pluck('id')->all());
    }

    public function test_in_consegna_prima_i_disponibili_poi_gli_altri_con_il_motivo(): void
    {
        $fuori = Attrezzatura::factory()->create(['marca' => 'ATOMIC', 'modello' => 'REDSTER']);
        app(Banco::class)->consegna(Cliente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']), [$fuori->id], today(), today());
        $daControllare = Attrezzatura::factory()->create(['marca' => 'ATOMIC', 'modello' => 'REDSTER', 'stato' => StatoAttrezzatura::DaControllare]);
        Attrezzatura::factory()->create(['marca' => 'ATOMIC', 'modello' => 'REDSTER', 'stato' => StatoAttrezzatura::FuoriServizio]);
        $libero = Attrezzatura::factory()->create(['marca' => 'ATOMIC', 'modello' => 'REDSTER']);

        $suggerimenti = Livewire::test(ConsegnaOperazione::class)
            ->call('apri')
            ->set('codice', 'atomic')
            ->instance()->getSuggerimenti();

        $this->assertCount(3, $suggerimenti, 'i pezzi fuori servizio non si propongono');
        $this->assertSame([$libero->codice, true, 'Disponibile'], [$suggerimenti[0]['codice'], $suggerimenti[0]['scegli'], $suggerimenti[0]['stato']]);
        $perCodice = collect($suggerimenti)->keyBy('codice');
        $this->assertSame('Fuori · Rossi Anna', $perCodice[$fuori->codice]['stato']);
        $this->assertFalse($perCodice[$daControllare->codice]['scegli']);
        $this->assertStringContainsString('img/tipologie/ski.svg', $suggerimenti[0]['immagine']);
    }

    public function test_un_solo_carattere_non_suggerisce(): void
    {
        Attrezzatura::factory()->create(['marca' => 'ATOMIC']);

        $this->assertSame([], Livewire::test(ConsegnaOperazione::class)->call('apri')->set('codice', 'a')->instance()->getSuggerimenti());
    }

    public function test_scegliere_un_suggerimento_lo_aggiunge(): void
    {
        $pezzo = Attrezzatura::factory()->create(['marca' => 'HEAD']);

        Livewire::test(ConsegnaOperazione::class)
            ->call('apri')
            ->set('codice', 'hea')
            ->call('aggiungi', $pezzo->codice)
            ->assertSet('pezzi', [$pezzo->id])
            ->assertSet('codice', '');
    }

    public function test_in_rientro_solo_i_pezzi_fuori_con_chi_li_ha(): void
    {
        [$fuori, $inMagazzino] = Attrezzatura::factory()->count(2)->create(['marca' => 'NITRO']);
        app(Banco::class)->consegna(Cliente::factory()->create(['cognome' => 'Bianchi', 'nome' => 'Luca']), [$fuori->id], today()->subDays(4), today()->subDays(2));

        $suggerimenti = Livewire::test(RientroOperazione::class)->call('apri')->set('codice', 'nitro')->instance()->getSuggerimenti();

        $this->assertSame([$fuori->codice], array_column($suggerimenti, 'codice'));
        $this->assertSame('Bianchi Luca · 2 gg di ritardo', $suggerimenti[0]['stato']);
        $this->assertNotSame($inMagazzino->codice, $suggerimenti[0]['codice']);
    }

    public function test_la_foto_nuova_vale_per_tutto_il_modello_solo_se_cambiata(): void
    {
        [$a, $b] = Attrezzatura::factory()->count(2)->create(['marca' => 'HEAD', 'modello' => 'KORE']);
        $altro = Attrezzatura::factory()->create(['marca' => 'HEAD', 'modello' => 'SHAPE', 'foto' => 'attrezzature/shape.jpg']);

        $a->update(['foto' => 'attrezzature/kore.jpg']);
        $this->assertSame(1, AttrezzaturaResource::propagaFoto($a, true));
        $this->assertSame('attrezzature/kore.jpg', $b->refresh()->foto);
        $this->assertSame('attrezzature/shape.jpg', $altro->refresh()->foto);

        $b->update(['foto' => 'attrezzature/altra.jpg']);
        $a->refresh()->update(['misura' => '999']); // diversa da qualunque misura della factory: il salvataggio avviene davvero
        $this->assertSame(0, AttrezzaturaResource::propagaFoto($a, true), 'salvare senza cambiare foto non tocca gli altri');
        $this->assertStringContainsString('/storage/attrezzature/kore.jpg', $a->immagine());
    }
}
