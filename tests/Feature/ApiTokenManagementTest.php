<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_personal_token(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->post('/perfil/tokens', ['name' => 'meu-script']);

        $response->assertRedirect(route('profile.tokens'));
        $response->assertSessionHas('novo_token');
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'meu-script',
        ]);
    }

    public function test_created_token_actually_authenticates_against_the_api(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post('/perfil/tokens', ['name' => 'meu-script']);
        $plainToken = session('novo_token');

        $this->assertNotNull($plainToken);

        $response = $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$plainToken}"]);
        $response->assertOk()->assertJson(['data' => ['type' => 'user', 'id' => $user->id]]);
    }

    public function test_user_can_revoke_own_token(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('a-revogar');

        $response = $this->actingAs($user)->delete("/perfil/tokens/{$token->accessToken->id}");

        $response->assertRedirect(route('profile.tokens'));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $user = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $token = $other->createToken('nao-e-meu');

        $this->actingAs($user)->delete("/perfil/tokens/{$token->accessToken->id}")->assertStatus(404);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_revoked_token_stops_authenticating(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('teste');

        $this->actingAs($user)->delete("/perfil/tokens/{$token->accessToken->id}");

        $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$token->plainTextToken}"])->assertStatus(401);
    }
}
