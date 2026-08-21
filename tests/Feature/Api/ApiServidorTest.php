<?php

namespace Tests\Feature\Api;

use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiServidorTest extends TestCase
{
    use RefreshDatabase;

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('teste')->plainTextToken];
    }

    public function test_admin_sees_servidores_from_all_empresas(): void
    {
        $admin = User::factory()->admin()->create();
        Servidor::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/servidores', $this->headersFor($admin));

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_cliente_sees_only_own_empresa_servidores(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresaA)->create();
        Servidor::factory()->for($empresaA)->create(['nome' => 'meu-servidor']);
        Servidor::factory()->for($empresaB)->create(['nome' => 'servidor-de-outra-empresa']);

        $response = $this->getJson('/api/v1/servidores', $this->headersFor($cliente));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('meu-servidor', $response->json('data.0.nome'));
    }

    public function test_empresa_api_key_sees_only_own_servidores(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();
        Servidor::factory()->for($empresaA)->create();
        Servidor::factory()->for($empresaB)->create();

        $response = $this->getJson('/api/v1/servidores', ['X-Api-Key' => $empresaA->api_key]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_cliente_cannot_view_another_empresas_servidor(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresaA)->create();
        $servidor = Servidor::factory()->for($empresaB)->create();

        $this->getJson("/api/v1/servidores/{$servidor->id}", $this->headersFor($cliente))->assertStatus(403);
    }

    public function test_admin_can_create_servidor_for_any_empresa(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();

        $response = $this->postJson('/api/v1/servidores', [
            'nome' => 'novo-servidor',
            'status' => 'active',
            'empresa_id' => $empresa->id,
        ], $this->headersFor($admin));

        $response->assertCreated();
        $this->assertDatabaseHas('servidores', ['nome' => 'novo-servidor', 'empresa_id' => $empresa->id]);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.rpz_url'));
    }

    public function test_cliente_cannot_create_servidor_without_active_licenca(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $response = $this->postJson('/api/v1/servidores', [
            'nome' => 'sem-licenca',
            'status' => 'active',
        ], $this->headersFor($cliente));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('servidores', ['nome' => 'sem-licenca']);
    }

    public function test_cliente_can_create_servidor_within_licenca_limit(): void
    {
        $empresa = Empresa::factory()->create();
        Licenca::factory()->for($empresa)->create(['max_servidores' => 1]);
        $cliente = User::factory()->cliente($empresa)->create();

        $response = $this->postJson('/api/v1/servidores', [
            'nome' => 'dentro-do-limite',
            'status' => 'active',
        ], $this->headersFor($cliente));

        $response->assertCreated();
        $this->assertDatabaseHas('servidores', ['nome' => 'dentro-do-limite', 'empresa_id' => $empresa->id]);
    }

    public function test_cliente_cannot_assign_servidor_to_another_empresa(): void
    {
        $empresaPropria = Empresa::factory()->create();
        Licenca::factory()->for($empresaPropria)->create(['max_servidores' => 5]);
        $empresaAlheia = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresaPropria)->create();

        $response = $this->postJson('/api/v1/servidores', [
            'nome' => 'tentativa-de-sequestro',
            'status' => 'active',
            'empresa_id' => $empresaAlheia->id,
        ], $this->headersFor($cliente));

        $response->assertCreated();
        $this->assertDatabaseHas('servidores', ['nome' => 'tentativa-de-sequestro', 'empresa_id' => $empresaPropria->id]);
    }

    public function test_cliente_cannot_delete_another_empresas_servidor(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresaA)->create();
        $servidor = Servidor::factory()->for($empresaB)->create();

        $this->deleteJson("/api/v1/servidores/{$servidor->id}", [], $this->headersFor($cliente))->assertStatus(403);
        $this->assertDatabaseHas('servidores', ['id' => $servidor->id]);
    }
}
