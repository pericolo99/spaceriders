<?php

namespace Tests\Feature;

use App\Enums\Esito;
use App\Enums\StatoAttrezzatura;
use App\Models\Attrezzatura;
use App\Models\Cliente;
use App\Models\Noleggio;
use App\Noleggi\Banco;
use App\Noleggi\BancoException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BancoTest extends TestCase
{
    use RefreshDatabase;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->banco = app(Banco::class);
    }

    private function consegna(Cliente $cliente, Attrezzatura ...$pezzi): Noleggio
    {
        return $this->banco->consegna(
            $cliente,
            array_map(fn (Attrezzatura $p) => $p->id, $pezzi),
            today(),
            today()->addDays(3),
            sacche: ['sci' => 2],
        );
    }

    public function test_la_consegna_crea_il_noleggio_con_una_riga_per_pezzo(): void
    {
        [$sci, $scarponi] = Attrezzatura::factory()->count(2)->create();

        $noleggio = $this->consegna(Cliente::factory()->create(), $sci, $scarponi);

        $this->assertTrue($noleggio->isAperto());
        $this->assertSame(2, $noleggio->righeAperte()->count());
        $this->assertSame('2 sci', $noleggio->sacche());
        $this->assertFalse($sci->isDisponibile());
    }

    public function test_un_pezzo_gia_fuori_non_si_consegna_di_nuovo(): void
    {
        $sci = Attrezzatura::factory()->create();
        $this->consegna(Cliente::factory()->create(), $sci);

        try {
            $this->banco->pezzoDaConsegnare($sci->codice);
            $this->fail('Il pezzo è già fuori: doveva essere rifiutato.');
        } catch (BancoException $e) {
            $this->assertSame('Pezzo già fuori', $e->titolo);
            $this->assertStringContainsString('è già fuori con', $e->getMessage());
        }
    }

    public function test_un_pezzo_fuori_servizio_non_si_consegna(): void
    {
        $sci = Attrezzatura::factory()->create(['stato' => StatoAttrezzatura::FuoriServizio]);

        $this->expectException(BancoException::class);

        $this->banco->pezzoDaConsegnare($sci->codice);
    }

    public function test_un_codice_sconosciuto_dice_quale_codice(): void
    {
        $this->expectExceptionMessage('«999»');

        $this->banco->pezzoDaConsegnare('999');
    }

    public function test_il_codice_gestionale_vale_solo_se_e_di_un_pezzo_solo(): void
    {
        $unico = Attrezzatura::factory()->create(['codice_gestionale' => 'G1']);
        Attrezzatura::factory()->count(2)->create(['codice_gestionale' => 'G2']);

        $this->assertTrue(Attrezzatura::perCodice('G1')->is($unico));
        $this->assertNull(Attrezzatura::perCodice('G2'));
    }

    public function test_il_noleggio_si_chiude_quando_rientra_l_ultimo_pezzo(): void
    {
        [$sci, $scarponi] = Attrezzatura::factory()->count(2)->create();
        $noleggio = $this->consegna(Cliente::factory()->create(), $sci, $scarponi);

        $this->banco->rientro($this->banco->rigaDaRientrare($sci->codice));
        $this->assertTrue($noleggio->refresh()->isAperto());

        $this->banco->rientro($this->banco->rigaDaRientrare($scarponi->codice));
        $this->assertFalse($noleggio->refresh()->isAperto());
        $this->assertTrue($sci->refresh()->isDisponibile());
    }

    public function test_un_rientro_danneggiato_mette_il_pezzo_fuori_servizio_e_lo_segnala(): void
    {
        $sci = Attrezzatura::factory()->create();
        $noleggio = $this->consegna(Cliente::factory()->create(), $sci);

        $this->banco->rientro($this->banco->rigaDaRientrare($sci->codice), Esito::Danneggiato, 'Soletta rigata', ['segnalazioni/a.jpg']);

        $sci->refresh();
        $this->assertSame(StatoAttrezzatura::FuoriServizio, $sci->stato);
        $segnalazione = $sci->segnalazioni()->sole();
        $this->assertSame(Esito::Danneggiato, $segnalazione->esito);
        $this->assertSame($noleggio->cliente_id, $segnalazione->cliente_id);
        $this->assertSame(['segnalazioni/a.jpg'], $segnalazione->foto);
    }

    public function test_da_controllare_blocca_il_pezzo_finche_non_si_rimette_in_servizio(): void
    {
        $sci = Attrezzatura::factory()->create();
        $this->consegna(Cliente::factory()->create(), $sci);
        $this->banco->rientro($this->banco->rigaDaRientrare($sci->codice), Esito::DaControllare);

        $this->assertFalse($sci->refresh()->isDisponibile());

        $this->banco->rimettiInServizio($sci);

        $this->assertTrue($sci->refresh()->isDisponibile());
        $this->assertNotNull($sci->segnalazioni()->sole()->risolta_at);
    }

    public function test_un_pezzo_non_fuori_non_si_fa_rientrare(): void
    {
        $sci = Attrezzatura::factory()->create();

        $this->expectExceptionMessage('non risulta fuori');

        $this->banco->rigaDaRientrare($sci->codice);
    }

    public function test_il_rientro_non_puo_precedere_la_consegna(): void
    {
        $sci = Attrezzatura::factory()->create();

        $this->expectException(BancoException::class);

        $this->banco->consegna(Cliente::factory()->create(), [$sci->id], today(), today()->subDay());
    }

    public function test_in_ritardo_conta_solo_i_noleggi_aperti_scaduti(): void
    {
        [$a, $b] = Attrezzatura::factory()->count(2)->create();
        $cliente = Cliente::factory()->create();
        $scaduto = $this->banco->consegna($cliente, [$a->id], today()->subDays(5), today()->subDays(2));
        $this->banco->consegna($cliente, [$b->id], today(), today()->addDay());

        $this->assertSame([$scaduto->id], Noleggio::inRitardo()->pluck('id')->all());
        $this->assertSame(2, $scaduto->refresh()->giorniDiRitardo());
    }

    public function test_da_pagare_e_un_promemoria_che_si_spegne_quando_paga(): void
    {
        $noleggio = $this->banco->consegna(
            Cliente::factory()->create(),
            [Attrezzatura::factory()->create()->id],
            today(),
            today()->addDay(),
            daPagare: true,
            notePagamento: 'paga con il gruppo',
        );

        $this->assertTrue($noleggio->da_pagare);
        $this->assertSame('paga con il gruppo', $noleggio->note_pagamento);

        $this->banco->segnaPagato($noleggio);

        $this->assertFalse($noleggio->refresh()->da_pagare);
        $this->assertNotNull($noleggio->pagato_at);
        $this->assertSame('paga con il gruppo', $noleggio->note_pagamento);
    }

    public function test_la_nota_sul_pagamento_si_salva_anche_senza_da_pagare(): void
    {
        $noleggio = $this->banco->consegna(
            Cliente::factory()->create(),
            [Attrezzatura::factory()->create()->id],
            today(),
            today()->addDay(),
            notePagamento: ' ha già pagato con bonifico ',
        );

        $this->assertFalse($noleggio->da_pagare);
        $this->assertSame('ha già pagato con bonifico', $noleggio->note_pagamento);
    }
}
