<?php

namespace Tests\Feature\Api;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiListaDominioTest extends TestCase
{
    use RefreshDatabase;

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('teste')->plainTextToken];
    }

    public function test_cliente_cannot_create_lista(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->postJson('/api/v1/listas', [
            'nome' => 'tentativa',
            'status' => 'active',
        ], $this->headersFor($cliente))->assertStatus(403);
    }

    public function test_admin_can_create_manual_lista_and_add_dominios(): void
    {
        $admin = User::factory()->admin()->create();

        $listaResponse = $this->postJson('/api/v1/listas', [
            'nome' => 'Lista via API',
            'status' => 'active',
        ], $this->headersFor($admin));

        $listaResponse->assertCreated();
        $listaId = $listaResponse->json('data.id');

        $dominioResponse = $this->postJson("/api/v1/listas/{$listaId}/dominios", [
            'dominio' => 'malicioso.example',
        ], $this->headersFor($admin));

        $dominioResponse->assertCreated();
        $this->assertDatabaseHas('dominios', ['lista_id' => $listaId, 'dominio' => 'malicioso.example']);
    }

    public function test_bulk_import_reports_added_duplicated_and_invalid(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'ja-existe.example']);

        $response = $this->postJson("/api/v1/listas/{$lista->id}/dominios/bulk", [
            'dominios' => ['novo.example', 'ja-existe.example', 'nao e um dominio valido'],
        ], $this->headersFor($admin));

        $response->assertCreated();
        $this->assertSame(1, $response->json('data.adicionados'));
        $this->assertSame(1, $response->json('data.duplicados'));
        $this->assertSame(1, $response->json('data.invalidos'));
    }

    public function test_cannot_manually_edit_domains_of_external_lista(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->externa('feed', 'https://feed.example/hosts.txt')->create();

        $this->postJson("/api/v1/listas/{$lista->id}/dominios", [
            'dominio' => 'nao-deveria-entrar.example',
        ], $this->headersFor($admin))->assertStatus(422);
    }

    public function test_cliente_can_view_catalog_lista_but_not_edit_it(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $catalogo = Lista::factory()->create(['empresa_id' => null]);

        $this->getJson("/api/v1/listas/{$catalogo->id}", $this->headersFor($cliente))->assertOk();
        $this->postJson("/api/v1/listas/{$catalogo->id}/dominios", [
            'dominio' => 'teste.example',
        ], $this->headersFor($cliente))->assertStatus(403);
    }

    public function test_cliente_cannot_view_another_empresas_private_lista(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresaA)->create();
        $listaAlheia = Lista::factory()->create(['empresa_id' => $empresaB->id]);

        $this->getJson("/api/v1/listas/{$listaAlheia->id}", $this->headersFor($cliente))->assertStatus(403);
    }
}
