<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\ServerAllowedIp;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MikrotikHostsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_feed_returns_deduplicated_hosts_format_and_records_sync(): void
    {
        $empresa = Empresa::factory()->create(['rpz_slug' => 'mikrotik-test']);
        $servidor = Servidor::factory()->for($empresa)->create(['tipo_dns' => 'mikrotik']);
        ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => '127.0.0.1/32', 'status' => 'active']);
        $lista = Lista::factory()->create(['status' => 'active']);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'Bloqueado.Example']);
        Dominio::factory()->for($lista)->create(['dominio' => 'bloqueado.example']);
        Dominio::factory()->for($lista)->inativo()->create(['dominio' => 'liberado.example']);

        $response = $this->get('/mikrotik/mikrotik-test.hosts');

        $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $content = $response->streamedContent();
        $this->assertSame(1, preg_match_all('/^0\.0\.0\.0 bloqueado\.example$/m', $content));
        $this->assertStringNotContainsString('liberado.example', $content);
        $this->assertStringNotContainsString('CNAME', $content);
        $this->assertDatabaseHas('server_sync_logs', ['servidor_id' => $servidor->id, 'dominios_count' => 1]);
    }

    public function test_company_feed_requires_acl_and_token_feed_requires_at_least_one_registered_ip(): void
    {
        $empresa = Empresa::factory()->create(['rpz_slug' => 'mikrotik-acl']);
        $servidor = Servidor::factory()->for($empresa)->create(['tipo_dns' => 'mikrotik']);

        $this->get('/mikrotik/mikrotik-acl.hosts')->assertForbidden();
        // Sem nenhum IP cadastrado, o token tambem nao funciona -- nao ha mais
        // bypass total so por ter ip_restriction_enabled desligado.
        $this->get("/mikrotik/{$servidor->token}.hosts")->assertNotFound();

        ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => '127.0.0.1/32', 'status' => 'active']);
        $this->get("/mikrotik/{$servidor->token}.hosts")->assertOk();

        $servidor->update(['ip_restriction_enabled' => true]);
        $this->get("/mikrotik/{$servidor->token}.hosts")->assertOk();

        $servidor->allowedIps()->update(['ip_cidr' => '203.0.113.0/24']);
        $this->get("/mikrotik/{$servidor->token}.hosts")->assertNotFound();
    }

    public function test_mikrotik_server_page_shows_adlist_command_and_hosts_url(): void
    {
        $empresa = Empresa::factory()->create(['rpz_slug' => 'routeros']);
        $servidor = Servidor::factory()->for($empresa)->create(['tipo_dns' => 'mikrotik']);
        $cliente = User::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)->get(route('servidores.show', $servidor))
            ->assertOk()
            ->assertSee('Configuração do MikroTik')
            ->assertSee('/ip dns adlist add')
            ->assertSee('/mikrotik/routeros.hosts');
    }

    public function test_mikrotik_selection_forces_supported_block_mode(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)->post(route('servidores.store'), [
            'nome' => 'Router da borda',
            'status' => 'active',
            'tipo_dns' => 'mikrotik',
            'bloqueio_modo' => 'redirect',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('servidores', [
            'nome' => 'Router da borda',
            'tipo_dns' => 'mikrotik',
            'bloqueio_modo' => 'nxdomain',
        ]);
    }
}
