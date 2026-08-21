<?php

namespace Tests\Feature\Api;

use App\Models\Empresa;
use App\Models\EmpresaAllowedIp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiIpRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_blocked_from_disallowed_ip_when_restriction_enabled(): void
    {
        $empresa = Empresa::factory()->create(['ip_restriction_enabled' => true]);
        EmpresaAllowedIp::create(['empresa_id' => $empresa->id, 'ip_cidr' => '203.0.113.0/24', 'status' => 'active']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.5'])
            ->getJson('/api/v1/listas', ['X-Api-Key' => $empresa->api_key]);

        $response->assertStatus(403);
    }

    public function test_api_key_allowed_from_whitelisted_ip(): void
    {
        $empresa = Empresa::factory()->create(['ip_restriction_enabled' => true]);
        EmpresaAllowedIp::create(['empresa_id' => $empresa->id, 'ip_cidr' => '203.0.113.0/24', 'status' => 'active']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->getJson('/api/v1/listas', ['X-Api-Key' => $empresa->api_key]);

        $response->assertOk();
    }

    public function test_api_key_works_from_anywhere_when_restriction_disabled(): void
    {
        $empresa = Empresa::factory()->create(['ip_restriction_enabled' => false]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
            ->getJson('/api/v1/listas', ['X-Api-Key' => $empresa->api_key]);

        $response->assertOk();
    }

    public function test_cliente_token_also_respects_empresa_ip_restriction(): void
    {
        $empresa = Empresa::factory()->create(['ip_restriction_enabled' => true]);
        EmpresaAllowedIp::create(['empresa_id' => $empresa->id, 'ip_cidr' => '203.0.113.0/24', 'status' => 'active']);
        $cliente = User::factory()->cliente($empresa)->create();
        $token = $cliente->createToken('teste')->plainTextToken;

        $blocked = $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson('/api/v1/listas', ['Authorization' => "Bearer {$token}"]);
        $blocked->assertStatus(403);

        $allowed = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->getJson('/api/v1/listas', ['Authorization' => "Bearer {$token}"]);
        $allowed->assertOk();
    }

    public function test_admin_token_bypasses_empresa_ip_restriction(): void
    {
        $admin = User::factory()->admin()->create();
        Empresa::factory()->create(['ip_restriction_enabled' => true]);
        $token = $admin->createToken('teste')->plainTextToken;

        $response = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->getJson('/api/v1/listas', ['Authorization' => "Bearer {$token}"]);

        $response->assertOk();
    }

    public function test_admin_can_toggle_empresa_ip_restriction_via_web(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create(['ip_restriction_enabled' => false]);

        $this->actingAs($admin)->post(route('empresas.ip-restriction.toggle', $empresa));

        $this->assertTrue($empresa->fresh()->ip_restriction_enabled);
    }

    public function test_admin_can_add_and_remove_allowed_ip_via_web(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();

        $this->actingAs($admin)->post(route('empresas.ips.store', $empresa), ['ip_cidr' => '203.0.113.10']);
        $this->assertDatabaseHas('empresa_allowed_ips', ['empresa_id' => $empresa->id, 'ip_cidr' => '203.0.113.10']);

        $ip = $empresa->allowedIps()->first();
        $this->actingAs($admin)->delete(route('empresas.ips.destroy', [$empresa, $ip]));
        $this->assertDatabaseMissing('empresa_allowed_ips', ['id' => $ip->id]);
    }

    public function test_invalid_ip_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();

        $response = $this->actingAs($admin)->post(route('empresas.ips.store', $empresa), ['ip_cidr' => 'nao-e-um-ip']);

        $response->assertSessionHasErrors('ip_cidr');
    }
}
