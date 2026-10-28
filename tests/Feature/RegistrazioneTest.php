<?php

namespace Tests\Feature;

use App\Filament\Pubblico\Registrazione;
use App\Models\Cliente;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrazioneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('service');
    }

    /** @return array<string, mixed> */
    private function dati(array $altro = []): array
    {
        return [
            'nome' => "maria d'angelo",
            'cognome' => 'rossi',
            'codice_fiscale' => 'rssmra90a41h501x',
            'data_nascita' => '1990-01-01',
            'luogo_nascita' => 'Roma',
            'telefono' => '333 1234567',
            'email' => 'maria@example.it',
            'citta' => 'Roma',
            'consenso' => true,
            ...$altro,
        ];
    }

    public function test_la_pagina_si_apre_senza_accesso(): void
    {
        $this->get('/registrazione')
            ->assertOk()
            ->assertSee('Registrazione')
            ->assertSee('Codice fiscale')
            // È dei clienti: non propone di installare l'app del banco.
            ->assertDontSee('manifest.webmanifest');
    }

    public function test_il_vecchio_indirizzo_porta_alla_registrazione(): void
    {
        $this->get('/public/registrazione')->assertRedirect('/registrazione')->assertStatus(301);
    }

    public function test_il_cliente_si_registra_da_solo(): void
    {
        Livewire::test(Registrazione::class)
            ->fillForm($this->dati())
            ->call('registra')
            ->assertHasNoFormErrors()
            ->assertSet('registrato', "Maria D'Angelo")
            ->assertSee('Al banco basta dire nome e cognome');

        $cliente = Cliente::sole();
        $this->assertSame("Rossi Maria D'Angelo", $cliente->nominativo);
        $this->assertSame('RSSMRA90A41H501X', $cliente->codice_fiscale);
        $this->assertSame('portale', $cliente->origine);
        $this->assertNotNull($cliente->consenso_at);
    }

    public function test_senza_consenso_non_si_registra(): void
    {
        Livewire::test(Registrazione::class)
            ->fillForm($this->dati(['consenso' => false]))
            ->call('registra')
            ->assertHasFormErrors(['consenso' => 'accepted']);

        $this->assertSame(0, Cliente::count());
    }

    public function test_un_codice_fiscale_gia_presente_non_si_registra_due_volte(): void
    {
        Cliente::factory()->create(['codice_fiscale' => 'RSSMRA90A41H501X', 'nome' => 'Vero']);

        Livewire::test(Registrazione::class)
            ->fillForm($this->dati())
            ->call('registra')
            ->assertHasErrors(['dati.codice_fiscale'])
            ->assertSet('registrato', null);

        $this->assertSame(1, Cliente::count());
        $this->assertSame('Vero', Cliente::sole()->nome);
    }

    public function test_si_puo_registrare_un_familiare_subito_dopo(): void
    {
        Livewire::test(Registrazione::class)
            ->fillForm($this->dati())
            ->call('registra')
            ->call('altraPersona')
            ->assertSet('registrato', null)
            ->fillForm($this->dati(['nome' => 'Luca', 'codice_fiscale' => 'RSSLCU15C10H501Y']))
            ->call('registra')
            ->assertSet('registrato', 'Luca');

        $this->assertSame(2, Cliente::count());
    }
}
