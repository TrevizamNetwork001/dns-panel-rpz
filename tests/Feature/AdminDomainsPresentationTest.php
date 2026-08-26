<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDomainsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pagination_is_portuguese_and_preserves_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        foreach (range(1, 51) as $index) {
            Dominio::factory()->for($lista)->create([
                'dominio' => sprintf('site-%03d.example', $index),
                'ativo' => true,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('dominios.index', [
            'dominio' => 'site-',
            'status' => 'ativos',
            'fonte_id' => $lista->id,
        ]));

        $response->assertOk()
            ->assertSee('Anterior')
            ->assertSee('Próxima')
            ->assertSee('Página 1')
            ->assertSee('dominio=site-&amp;status=ativos&amp;fonte_id='.$lista->id.'&amp;page=2', false)
            ->assertDontSee('Previous')
            ->assertDontSee('Next');
    }

    public function test_admin_source_summary_and_actions_match_real_domain_state(): void
    {
        $admin = User::factory()->admin()->create();
        $primeira = Lista::factory()->create(['nome' => 'Phishing Army']);
        $segunda = Lista::factory()->externa('threatfox')->create(['nome' => 'ThreatFox — C2 / Botnet']);

        Dominio::factory()->for($primeira)->create(['dominio' => 'duplicado.example', 'ativo' => true]);
        Dominio::factory()->for($segunda)->create(['dominio' => 'duplicado.example', 'ativo' => true]);
        Dominio::factory()->for($primeira)->create(['dominio' => 'unico.example', 'ativo' => false]);

        $response = $this->actingAs($admin)->get(route('dominios.index'));

        $response->assertOk()
            ->assertSee('Phishing Army +1')
            ->assertSee('Phishing Army, ThreatFox — C2 / Botnet')
            ->assertSee('Ações do domínio unico.example')
            ->assertSee('Ver fonte')
            ->assertSee('Ativar')
            ->assertSee('actions-menu-item-danger')
            ->assertSee('Remover');
    }

    public function test_domain_listing_uses_a_bounded_number_of_queries_for_a_full_page(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        foreach (range(1, 50) as $index) {
            Dominio::factory()->for($lista)->create(['dominio' => sprintf('volume-%03d.example', $index)]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($admin)->get(route('dominios.index'))->assertOk();

        $this->assertLessThan(20, $queries, 'A listagem não deve executar uma query por domínio.');
    }

    public function test_managed_source_does_not_offer_unsupported_domain_mutations(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->anatel()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'anatel-gerenciado.example']);

        $this->actingAs($admin)->get(route('dominios.index'))
            ->assertOk()
            ->assertSee('Ações do domínio anatel-gerenciado.example')
            ->assertSee('Ver fonte')
            ->assertDontSee('Desativar')
            ->assertDontSee('Remover este domínio?');
    }

    public function test_client_keeps_the_existing_default_pagination_view(): void
    {
        $cliente = User::factory()->cliente()->create();
        $lista = Lista::factory()->create(['empresa_id' => null]);

        foreach (range(1, 51) as $index) {
            Dominio::factory()->for($lista)->create(['dominio' => sprintf('cliente-%03d.example', $index)]);
        }

        $this->actingAs($cliente)->get(route('dominios.index'))
            ->assertOk()
            ->assertDontSee('admin-domains-pagination');
    }
}
