<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSourcesPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_listing_formats_counts_types_dates_and_real_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        Lista::factory()->create(['nome' => 'ANATEL', 'origem' => 'anatel', 'empresa_id' => null]);
        $externa = Lista::factory()->externa()->create(['nome' => 'Anatel', 'empresa_id' => null, 'last_sync_at' => now()->subHours(2)]);
        Lista::factory()->create(['nome' => 'Fonte própria', 'origem' => 'manual', 'empresa_id' => $empresa->id]);
        Dominio::factory()->for($externa)->count(1001)->create();

        $response = $this->actingAs($admin)->get(route('listas.index'));

        $response->assertOk()
            ->assertSee('admin-fontes-table')
            ->assertSee('1.001')
            ->assertSee('há 2 h')
            ->assertSee('Catálogo')
            ->assertSee('Externa')
            ->assertSee('Própria')
            ->assertSee('Ver detalhes')
            ->assertSee('Histórico')
            ->assertSee('Pausar sincronização');
    }

    public function test_source_detail_uses_limited_preview_and_operational_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->externa()->create(['last_sync_at' => now()->subDay()]);
        Dominio::factory()->for($lista)->count(11)->sequence(
            fn ($sequence) => ['dominio' => sprintf('dominio-%02d.example', $sequence->index)]
        )->create();

        $response = $this->actingAs($admin)->get(route('listas.show', $lista));

        $response->assertOk()
            ->assertSee('Resumo operacional')
            ->assertSee('Domínios (11)')
            ->assertSee('mostrando os primeiros 10 de 11 domínios')
            ->assertSee('dominio-09.example')
            ->assertDontSee('dominio-10.example')
            ->assertSee('há 1 dia');
    }

    public function test_admin_form_keeps_real_feed_minimum_and_distribution_context(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->externa()->create(['empresa_id' => null]);

        $this->actingAs($admin)->get(route('listas.edit', $lista))
            ->assertOk()
            ->assertSee('rows="3"', false)
            ->assertSee('mínimo de 100 domínios')
            ->assertSee('Endpoints RPZ vinculados')
            ->assertSee('a distribuição pode incluir endpoints de todas as empresas');
    }

    public function test_client_listing_does_not_receive_admin_sources_scope(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente()->for($empresa)->create();
        Lista::factory()->for($empresa)->create();

        $this->actingAs($cliente)->get(route('listas.index'))
            ->assertOk()
            ->assertDontSee('admin-fontes-page')
            ->assertDontSee('admin-fontes-table');
    }
}
