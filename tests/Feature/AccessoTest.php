<?php

namespace Tests\Feature;

use App\Filament\Auth\Accesso;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('service');
    }

    public function test_si_entra_con_un_nome_utente_senza_chiocciola(): void
    {
        $utente = User::factory()->create(['email' => 'banco1', 'password' => 'segreta-123']);

        Livewire::test(Accesso::class)
            ->fillForm(['email' => ' Banco1 ', 'password' => 'segreta-123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($utente);
    }

    public function test_si_entra_anche_con_l_email(): void
    {
        $utente = User::factory()->create(['email' => 'ufficio@example.it', 'password' => 'segreta-123']);

        Livewire::test(Accesso::class)
            ->fillForm(['email' => 'ufficio@example.it', 'password' => 'segreta-123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($utente);
    }

    public function test_la_password_sbagliata_non_entra(): void
    {
        User::factory()->create(['email' => 'banco1', 'password' => 'segreta-123']);

        Livewire::test(Accesso::class)
            ->fillForm(['email' => 'banco1', 'password' => 'altra'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }
}
