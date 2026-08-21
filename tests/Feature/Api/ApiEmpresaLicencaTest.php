<?php

namespace Tests\Feature\Api;

use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEmpresaLicencaTest extends TestCase
{
    use RefreshDatabase;

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('teste')->plainTextToken];
    }

    public function test_admin_sees_all_empresas(): void
    {
        $admin = User::factory()->admin()->create();
        Empresa::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/empresas', $this->headersFor($admin));

        $this->assertCount(3, $response->json('data'));
    }

    public function test_cliente_sees_only_own_empresa(): void
    {
        $empresaPropria = Empresa::factory()->create();
        Empresa::factory()->count(2)->create();
        $cliente = User::factory()->cliente($empresaPropria)->create();

        $response = $this->getJson('/api/v1/empresas', $this->headersFor($cliente));

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($empresaPropria->id, $response->json('data.0.id'));
    }

    public function test_cliente_cannot_update_empresa(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->putJson("/api/v1/empresas/{$empresa->id}", [
            'nome' => 'nome alterado',
            'status' => 'active',
        ], $this->headersFor($cliente))->assertStatus(403);
    }

    public function test_cliente_sees_only_own_licencas(): void
    {
        $empresaPropria = Empresa::factory()->create();
        $empresaAlheia = Empresa::factory()->create();
        Licenca::factory()->for($empresaPropria)->create();
        Licenca::factory()->for($empresaAlheia)->create();
        $cliente = User::factory()->cliente($empresaPropria)->create();

        $response = $this->getJson('/api/v1/licencas', $this->headersFor($cliente));

        $this->assertCount(1, $response->json('data'));
    }

    public function test_cliente_cannot_create_licenca(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->postJson('/api/v1/licencas', [
            'empresa_id' => $empresa->id,
            'starts_at' => now()->toDateString(),
            'max_servidores' => 100,
            'status' => 'active',
        ], $this->headersFor($cliente))->assertStatus(403);
    }

    public function test_admin_can_create_and_update_licenca(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();

        $response = $this->postJson('/api/v1/licencas', [
            'empresa_id' => $empresa->id,
            'starts_at' => now()->toDateString(),
            'max_servidores' => 10,
            'status' => 'active',
        ], $this->headersFor($admin));

        $response->assertCreated();
        $this->assertDatabaseHas('licencas', ['empresa_id' => $empresa->id, 'max_servidores' => 10]);
    }

    public function test_empresa_api_key_cannot_create_empresa_or_licenca(): void
    {
        $empresa = Empresa::factory()->create();

        $this->postJson('/api/v1/empresas', [
            'nome' => 'via chave',
            'status' => 'active',
        ], ['X-Api-Key' => $empresa->api_key])->assertStatus(403);

        $this->postJson('/api/v1/licencas', [
            'empresa_id' => $empresa->id,
            'starts_at' => now()->toDateString(),
            'max_servidores' => 10,
            'status' => 'active',
        ], ['X-Api-Key' => $empresa->api_key])->assertStatus(403);
    }
}
