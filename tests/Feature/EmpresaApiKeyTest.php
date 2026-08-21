<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresa_gets_an_api_key_automatically_on_creation(): void
    {
        $empresa = Empresa::factory()->create();

        $this->assertNotNull($empresa->api_key);
        $this->assertSame(48, strlen($empresa->api_key));
    }

    public function test_admin_can_regenerate_empresa_api_key(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $chaveAntiga = $empresa->api_key;

        $response = $this->actingAs($admin)->post(route('empresas.regenerate-api-key', $empresa));

        $response->assertRedirect();
        $this->assertNotSame($chaveAntiga, $empresa->fresh()->api_key);
    }

    public function test_old_key_stops_working_after_regeneration(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $chaveAntiga = $empresa->api_key;

        $this->actingAs($admin)->post(route('empresas.regenerate-api-key', $empresa));

        $this->getJson('/api/v1/user', ['X-Api-Key' => $chaveAntiga])->assertStatus(401);
    }

    public function test_cliente_cannot_regenerate_api_key(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)->post(route('empresas.regenerate-api-key', $empresa))->assertForbidden();
    }
}
