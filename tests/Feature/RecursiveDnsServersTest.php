<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\RecursiveDnsServer;
use App\Models\User;
use App\Services\RecursiveDnsDiagnostic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecursiveDnsServersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registers_a_resolver_and_runs_diagnostics_without_exposing_the_ssh_password(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Empresa::factory()->create();
        $password = 'example-test-password';

        $this->withoutVite()->actingAs($admin)->post(route('dns-recursivo.store'), [
            'name' => 'Recursivo SP',
            'hostname' => 'dns-sp-01',
            'empresa_id' => $company->id,
            'ipv6' => '2001:db8::10',
            'ssh_family' => 6,
            'ssh_port' => 2222,
            'ssh_user' => 'operador',
            'ssh_fingerprint' => 'SHA256:'.str_repeat('a', 43),
            'password' => $password,
        ])->assertSessionHasNoErrors();

        $server = RecursiveDnsServer::sole();
        $this->assertTrue($server->empresa->is($company));
        $this->assertSame('2001:db8::10', $server->sshAddress());
        $this->assertSame($password, $server->ssh_password);
        $this->assertNotSame($password, $server->getRawOriginal('ssh_password'));
        $this->assertArrayNotHasKey('ssh_password', $server->toArray());

        $this->mock(RecursiveDnsDiagnostic::class)->shouldReceive('run')->once()->andReturn([
            'hostname' => 'dns-sp-01',
            'unbound' => '0',
            'active' => '0',
        ]);
        $edit = route('dns-recursivo.edit', $server);
        $this->from($edit)->post(route('dns-recursivo.diagnose', $server))->assertRedirect($edit);
        $this->get($edit)->assertOk()->assertSee('Ativo')->assertDontSee($password);
    }

    public function test_resolver_inventory_is_restricted_to_administrators(): void
    {
        $this->actingAs(User::factory()->cliente()->create())->get(route('dns-recursivo.index'))->assertForbidden();
    }
}
