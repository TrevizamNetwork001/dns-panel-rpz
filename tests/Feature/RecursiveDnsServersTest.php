<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\RecursiveDnsServer;
use App\Models\User;
use App\Services\RecursiveDnsDiagnostic;
use App\Services\RecursiveDnsInstaller;
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

    public function test_install_requires_an_approved_preview_and_uses_its_profile_only_once(): void
    {
        $this->withoutVite()->actingAs(User::factory()->admin()->create());
        $profile = ['threads' => 2, 'message_cache' => 160, 'record_cache' => 320];
        $server = RecursiveDnsServer::create([
            'name' => 'Recursivo teste',
            'hostname' => 'dns-teste',
            'ipv4' => '192.0.2.10',
            'ssh_family' => 4,
            'ssh_port' => 22,
            'ssh_user' => 'operador',
            'ssh_fingerprint' => 'SHA256:'.str_repeat('a', 43),
            'ssh_password' => 'test-password',
            'diagnostic' => [
                'hostname' => 'dns-teste',
                'cpu' => '4',
                'ram_mib' => '4096',
                'unbound' => '1',
                'active' => '3',
                'port_53' => '0',
                'recommended' => ['threads' => 4, 'message_cache' => 256, 'record_cache' => 1024],
            ],
            'diagnosed_at' => now(),
        ]);

        $installer = $this->partialMock(RecursiveDnsInstaller::class);
        $installer->shouldReceive('install')->once()->withArgs(
            fn (RecursiveDnsServer $target, array $selected): bool => $target->is($server) && $selected === $profile
        );
        $this->mock(RecursiveDnsDiagnostic::class)->shouldReceive('run')->once()->andReturn([
            'hostname' => 'dns-teste',
            'unbound' => '0',
            'active' => '0',
        ]);

        $apply = route('dns-recursivo.install.apply', $server);
        $this->post($apply)->assertSessionHasErrors('install');
        $preview = $this->post(route('dns-recursivo.install.preview', $server), $profile);
        $preview->assertOk()->assertSee('num-threads: 2')->assertSee('rrset-cache-size: 320m');
        $token = session('recursive_dns_install.token');

        $this->post($apply, ['plan' => $token, 'threads' => 99])
            ->assertSessionHas('dns_status', 'Unbound instalado. A consulta DNS pelo loopback foi validada.');
        $this->assertSame('0', $server->fresh()->diagnostic['active']);
        $this->post($apply, ['plan' => $token])->assertSessionHasErrors('install');
    }

    public function test_resolver_inventory_is_restricted_to_administrators(): void
    {
        $this->actingAs(User::factory()->cliente()->create())->get(route('dns-recursivo.index'))->assertForbidden();
    }
}
