<?php

namespace Tests\Feature\Api;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_credentials_is_rejected(): void
    {
        $this->getJson('/api/v1/user')->assertStatus(401);
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $this->getJson('/api/v1/user', ['Authorization' => 'Bearer token-invalido'])
            ->assertStatus(401);
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        $this->getJson('/api/v1/user', ['X-Api-Key' => 'chave-invalida'])
            ->assertStatus(401);
    }

    public function test_valid_personal_token_authenticates_as_user(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Teste']);
        $token = $admin->createToken('teste')->plainTextToken;

        $response = $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$token}"]);

        $response->assertOk()->assertJson(['data' => ['type' => 'user', 'name' => 'Admin Teste', 'role' => 'admin']]);
    }

    public function test_valid_empresa_api_key_authenticates_as_empresa(): void
    {
        $empresa = Empresa::factory()->create(['nome' => 'Speednet Teste']);

        $response = $this->getJson('/api/v1/user', ['X-Api-Key' => $empresa->api_key]);

        $response->assertOk()->assertJson(['data' => ['type' => 'empresa_key', 'empresa_id' => $empresa->id]]);
    }

    public function test_api_key_from_inactive_empresa_is_rejected(): void
    {
        $empresa = Empresa::factory()->inactive()->create();

        $this->getJson('/api/v1/user', ['X-Api-Key' => $empresa->api_key])->assertStatus(401);
    }

    public function test_revoked_token_no_longer_works(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('teste');
        $plainText = $token->plainTextToken;

        $admin->tokens()->delete();

        $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$plainText}"])->assertStatus(401);
    }
}
