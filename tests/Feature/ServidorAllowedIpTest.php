<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ServerAllowedIp;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServidorAllowedIpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Empresa $empresa;
    private Servidor $servidor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->empresa = Empresa::factory()->create(['rpz_slug' => 'empresa-acl']);
        $this->servidor = Servidor::factory()->for($this->empresa)->create(['status' => 'active']);
    }

    public function test_adds_ipv4_and_displays_it(): void
    {
        $this->add('203.0.113.10')->assertRedirect();
        $this->assertStored('203.0.113.10');
        $this->actingAs($this->admin)->get(route('servidores.show', $this->servidor))
            ->assertOk()->assertSee('203.0.113.10')->assertDontSee('Nenhum IP cadastrado.');
    }

    public function test_adds_ipv4_32(): void
    {
        $this->add('203.0.113.10/32')->assertRedirect();
        $this->assertStored('203.0.113.10/32');
    }

    public function test_adds_ipv6(): void
    {
        $this->add('2001:db8::10')->assertRedirect();
        $this->assertStored('2001:db8::10');
    }

    public function test_adds_ipv6_128(): void
    {
        $this->add('2001:db8::10/128')->assertRedirect();
        $this->assertStored('2001:db8::10/128');
    }

    public function test_rule_is_used_by_effective_company_acl(): void
    {
        $this->add('203.0.113.10/32');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get("/rpz/{$this->empresa->rpz_slug}.zone")
            ->assertOk();
    }

    public function test_invalid_ip_is_rejected_without_server_error(): void
    {
        $this->add('999.999.999.999/32')->assertRedirect()->assertSessionHasErrors('ip_cidr');
        $this->assertDatabaseCount('server_allowed_ips', 0);
    }

    public function test_duplicate_is_idempotent(): void
    {
        $this->add('203.0.113.10/32')->assertRedirect();
        $this->add('203.0.113.10/32')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, ServerAllowedIp::where('servidor_id', $this->servidor->id)->count());
    }

    public function test_removes_rule(): void
    {
        $this->add('203.0.113.10/32');
        $rule = ServerAllowedIp::where('servidor_id', $this->servidor->id)->firstOrFail();
        $this->actingAs($this->admin)->delete(route('servidores.ips.destroy', [$this->servidor, $rule]))
            ->assertRedirect();
        $this->assertDatabaseMissing('server_allowed_ips', ['id' => $rule->id]);
    }

    private function add(string $value)
    {
        return $this->actingAs($this->admin)->post(route('servidores.ips.store', $this->servidor), ['ip_cidr' => $value]);
    }

    private function assertStored(string $value): void
    {
        $this->assertDatabaseHas('server_allowed_ips', [
            'servidor_id' => $this->servidor->id,
            'ip_cidr' => $value,
            'status' => 'active',
        ]);
    }
}
