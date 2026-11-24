<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\TenantApiCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantRpzApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_mutations_require_write_access_and_a_tenant_owned_manual_source(): void
    {
        Bus::fake();
        $company = Empresa::factory()->create();
        $user = User::factory()->cliente($company)->create();
        $source = Lista::factory()->for($company)->create(['tenant_api_write' => true]);
        $otherSource = Lista::factory()->for(Empresa::factory())->create(['tenant_api_write' => true]);
        $server = Servidor::factory()->for($company)->create();
        $server->listas()->attach($source);
        $read = $this->issueToken($user, 'read');
        $write = $this->issueToken($user, 'write');

        $this->requestWithToken('POST', "/listas/{$source->id}/bloqueios", $read, ['dominio' => 'example.test'])
            ->assertForbidden();
        $this->requestWithToken('POST', "/listas/{$otherSource->id}/bloqueios", $write, ['dominio' => 'example.test'])
            ->assertNotFound();
        $rule = $this->requestWithToken('POST', "/listas/{$source->id}/bloqueios", $write, ['dominio' => 'WWW.Example.test.'])
            ->assertCreated()->assertJsonPath('data.dominio', 'example.test')->json('data.id');
        $this->requestWithToken('POST', "/listas/{$source->id}/bloqueios", $write, ['dominio' => 'example.test'])
            ->assertConflict();
        $this->requestWithToken('GET', '/publicacao', $read)->assertOk()->assertJsonPath('data.status', 'pendente');
        $this->requestWithToken('PATCH', "/bloqueios/{$rule}", $write, ['ativo' => false])
            ->assertOk()->assertJsonPath('data.ativo', false);
        $this->requestWithToken('DELETE', "/bloqueios/{$rule}", $write)->assertNoContent();
    }

    public function test_confirmation_is_bound_to_a_server_and_separate_from_a_download(): void
    {
        $company = Empresa::factory()->create();
        $user = User::factory()->cliente($company)->create();
        $server = Servidor::factory()->for($company)->create();
        Servidor::factory()->for(Empresa::factory())->create();
        $report = $this->issueToken($user, 'report', $server->id);
        $read = $this->issueToken($user, 'read');
        $data = ['zona' => 'empresa', 'serial' => 105];

        $this->requestWithToken('PUT', '/servidores/confirmacao', $read, $data)->assertForbidden();
        $this->requestWithToken('GET', '/servidores', $report)->assertForbidden();
        $this->requestWithToken('PUT', '/servidores/confirmacao', $report, $data)->assertNoContent();
        $this->requestWithToken('GET', '/servidores', $read)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ultima_confirmacao.serial', 105)
            ->assertJsonPath('data.0.ultima_entrega_rpz', null);
    }

    private function issueToken(User $user, string $scope, ?int $serverId = null): string
    {
        $token = 'rpzk_'.Str::random(64);
        TenantApiCredential::create([
            'empresa_id' => $user->empresa_id,
            'user_id' => $user->id,
            'servidor_id' => $serverId,
            'name' => $scope,
            'scope' => $scope,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }

    private function requestWithToken(string $method, string $path, string $token, array $data = []): \Illuminate\Testing\TestResponse
    {
        Auth::forgetGuards();

        return $this->json($method, '/api/v1'.$path, $data, ['Authorization' => 'Bearer '.$token]);
    }
}
