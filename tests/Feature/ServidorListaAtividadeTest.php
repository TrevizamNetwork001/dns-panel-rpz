<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServidorListaAtividadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_domains_added_today_in_a_linked_lista(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create();
        $servidor->listas()->attach($lista);

        Dominio::factory()->for($lista)->create(['dominio' => 'novo-vinculado.example']);

        $response = $this->actingAs($admin)->get(route('servidores.show', $servidor));

        $response->assertOk();
        $response->assertSee('Lista &quot;' . $lista->nome . '&quot; ganhou 1 domínio(s)', false);
    }

    public function test_shows_domains_removed_today_in_a_linked_lista(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create();
        $servidor->listas()->attach($lista);

        $dominio = Dominio::factory()->for($lista)->inativo()->create(['dominio' => 'removido-vinculado.example']);
        $dominio->forceFill(['updated_at' => now()])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('servidores.show', $servidor));

        $response->assertOk();
        $response->assertSee('perdeu 1 domínio(s)');
    }

    public function test_shows_sync_events_alongside_lista_events(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create();
        $servidor->listas()->attach($lista);

        Dominio::factory()->for($lista)->create();
        ServerSyncLog::create(['servidor_id' => $servidor->id, 'ip_address' => '203.0.113.10', 'dominios_count' => 42, 'created_at' => now()]);

        $response = $this->actingAs($admin)->get(route('servidores.show', $servidor));

        $response->assertOk();
        $response->assertSee('Sincronização');
        $response->assertSee('42 domínios entregues');
        $response->assertSee('203.0.113.10');
    }

    public function test_does_not_show_activity_from_a_lista_not_linked_to_this_servidor(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create();
        $outraEmpresa = Empresa::factory()->create();
        $listaAlheia = Lista::factory()->create(['nome' => 'Lista Nao Vinculada', 'empresa_id' => $outraEmpresa->id]);

        Dominio::factory()->for($listaAlheia)->create();

        $response = $this->actingAs($admin)->get(route('servidores.show', $servidor));

        $response->assertOk();
        $response->assertDontSee('Lista Nao Vinculada');
        $response->assertSee('Nenhum evento nos últimos 30 dias');
    }

    public function test_cliente_can_see_activity_of_own_servidor(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->create(['empresa_id' => $empresa->id]);
        $lista = Lista::factory()->create(['empresa_id' => null]);
        $servidor->listas()->attach($lista);

        Dominio::factory()->for($lista)->create();

        $response = $this->actingAs($cliente)->get(route('servidores.show', $servidor));

        $response->assertOk();
        $response->assertSee($lista->nome);
    }
}
