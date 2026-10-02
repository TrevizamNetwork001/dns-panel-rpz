<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ServerAllowedIp;
use App\Models\Servidor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServidorIpPendenteTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge(['nome' => 'DNS do cliente', 'status' => 'active', 'tipo_dns' => 'unbound', 'bloqueio_modo' => 'nxdomain'], $extra);
    }

    private function cliente(): array
    {
        $empresa = Empresa::factory()->create(['nome' => 'Provedor Teste']);

        return [$empresa, User::factory()->cliente($empresa)->create()];
    }

    private function licenciar(Empresa $empresa): void
    {
        $empresa->licencas()->create(['status' => 'active', 'max_servidores' => 5, 'starts_at' => now()->subDay(), 'expires_at' => null]);
    }

    public function test_client_must_inform_a_dns_ip_when_creating_a_server(): void
    {
        [$empresa, $cliente] = $this->cliente();
        $this->licenciar($empresa);

        $this->actingAs($cliente)->post(route('servidores.store'), $this->payload())
            ->assertSessionHasErrors('ip_v4');

        $this->assertSame(0, $empresa->servidores()->count());
    }

    public function test_ipv6_alone_satisfies_the_requirement(): void
    {
        [$empresa, $cliente] = $this->cliente();
        $this->licenciar($empresa);

        $this->actingAs($cliente)->post(route('servidores.store'), $this->payload(['ip_v6' => '2001:db8::10']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('server_allowed_ips', ['ip_cidr' => '2001:db8::10', 'status' => 'pending']);
    }

    public function test_informed_ip_becomes_pending_and_does_not_grant_access(): void
    {
        Http::fake();
        [$empresa, $cliente] = $this->cliente();
        $this->licenciar($empresa);

        $this->actingAs($cliente)->post(route('servidores.store'), $this->payload(['ip_v4' => '203.0.113.10']))->assertRedirect();

        $servidor = $empresa->servidores()->first();
        $this->assertDatabaseHas('server_allowed_ips', ['servidor_id' => $servidor->id, 'ip_cidr' => '203.0.113.10', 'status' => 'pending']);
        $this->assertFalse($servidor->ipAllowed('203.0.113.10'), 'IP pendente não pode receber a zona.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'servidor.ip_pendente', 'target_id' => $servidor->id]);
    }

    public function test_telegram_is_notified_when_an_ip_is_awaiting_release(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Setting::set('telegram_ativo', '1');
        Setting::setEncrypted('telegram_bot_token', 'token-de-teste');
        Setting::set('telegram_chat_id', '-1001');
        [$empresa, $cliente] = $this->cliente();
        $this->licenciar($empresa);

        $this->actingAs($cliente)->post(route('servidores.store'), $this->payload(['ip_v4' => '203.0.113.10']))->assertRedirect();

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'api.telegram.org')
            && str_contains($request['text'], 'IP aguardando liberação')
            && str_contains($request['text'], 'Provedor Teste')
            && str_contains($request['text'], '203.0.113.10'));
    }

    public function test_admin_can_approve_and_the_ip_starts_working(): void
    {
        Http::fake();
        [$empresa, $cliente] = $this->cliente();
        $this->licenciar($empresa);
        $this->actingAs($cliente)->post(route('servidores.store'), $this->payload(['ip_v4' => '203.0.113.10']));
        $servidor = $empresa->servidores()->first();
        $ip = $servidor->allowedIps()->first();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('servidores.ips.approve', [$servidor, $ip]))->assertRedirect();

        $this->assertSame('active', $ip->fresh()->status);
        $this->assertTrue($servidor->fresh()->ipAllowed('203.0.113.10'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'servidor.ips.approved', 'target_id' => $servidor->id]);
    }

    public function test_client_cannot_approve_its_own_ip(): void
    {
        Http::fake();
        [$empresa, $cliente] = $this->cliente();
        $this->licenciar($empresa);
        $this->actingAs($cliente)->post(route('servidores.store'), $this->payload(['ip_v4' => '203.0.113.10']));
        $servidor = $empresa->servidores()->first();
        $ip = $servidor->allowedIps()->first();

        $this->actingAs($cliente)->patch(route('servidores.ips.approve', [$servidor, $ip]))->assertForbidden();

        $this->assertSame('pending', $ip->fresh()->status);
    }

    public function test_approving_an_ip_of_another_server_is_a_404(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Servidor::factory()->create();
        $b = Servidor::factory()->create();
        $ipDeB = ServerAllowedIp::create(['servidor_id' => $b->id, 'ip_cidr' => '203.0.113.9', 'status' => 'pending']);

        $this->actingAs($admin)->patch(route('servidores.ips.approve', [$a, $ipDeB]))->assertNotFound();
    }

    public function test_admin_adding_the_same_ip_manually_activates_the_pending_one(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create();
        ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => '203.0.113.10', 'status' => 'pending']);

        $this->actingAs($admin)->post(route('servidores.ips.store', $servidor), ['ip_cidr' => '203.0.113.10'])->assertRedirect();

        $this->assertSame(1, $servidor->allowedIps()->count());
        $this->assertSame('active', $servidor->allowedIps()->first()->status);
    }

    public function test_client_editing_the_ip_replaces_pending_but_never_touches_active(): void
    {
        Http::fake();
        [$empresa, $cliente] = $this->cliente();
        $servidor = Servidor::factory()->for($empresa)->create(['ip_v4' => '203.0.113.10']);
        ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => '198.51.100.7', 'status' => 'active']);
        ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => '203.0.113.10', 'status' => 'pending']);

        $this->actingAs($cliente)->put(route('servidores.update', $servidor), $this->payload(['ip_v4' => '203.0.113.99']))->assertRedirect();

        $ips = $servidor->allowedIps()->pluck('status', 'ip_cidr')->all();
        $this->assertSame(['198.51.100.7' => 'active', '203.0.113.99' => 'pending'], $ips);
    }

    public function test_client_page_explains_the_pending_state_and_clears_after_release(): void
    {
        Http::fake();
        [$empresa, $cliente] = $this->cliente();
        $this->licenciar($empresa);
        $this->actingAs($cliente)->post(route('servidores.store'), $this->payload(['ip_v4' => '203.0.113.10']));
        $servidor = $empresa->servidores()->first();

        $this->get(route('servidores.show', $servidor))->assertOk()
            ->assertSeeText('Aguardando liberação pelo suporte')->assertSeeText('203.0.113.10');

        $servidor->allowedIps()->update(['status' => 'active']);

        $this->get(route('servidores.show', $servidor))->assertOk()
            ->assertDontSeeText('Aguardando liberação pelo suporte')->assertDontSeeText('Nenhum IP informado');
    }

    public function test_admin_sees_pending_state_release_button_and_list_badge(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create();
        ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => '203.0.113.10', 'status' => 'pending']);

        $this->actingAs($admin)->get(route('servidores.show', $servidor))->assertOk()
            ->assertSeeText('IP aguardando liberação')->assertSeeText('Aguardando liberação')
            ->assertSee(route('servidores.ips.approve', [$servidor, $servidor->allowedIps()->first()]), false);

        $this->get(route('servidores.index'))->assertOk()->assertSeeText('IP aguardando liberação');
    }

    public function test_admin_creating_a_server_without_ip_does_not_require_or_register_ips(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $empresa->licencas()->create(['status' => 'active', 'max_servidores' => 1, 'starts_at' => now()->subDay(), 'expires_at' => null]);

        $this->actingAs($admin)->post(route('servidores.store'), $this->payload(['empresa_id' => $empresa->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ServerAllowedIp::count());
    }

    public function test_admin_creating_a_server_releases_the_informed_ips_right_away(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $empresa->licencas()->create(['status' => 'active', 'max_servidores' => 1, 'starts_at' => now()->subDay(), 'expires_at' => null]);

        $this->actingAs($admin)->post(route('servidores.store'), $this->payload([
            'empresa_id' => $empresa->id, 'ip_v4' => '203.0.113.10', 'ip_v6' => '2001:db8::10',
        ]))->assertRedirect()->assertSessionHas('status');

        $servidor = $empresa->servidores()->first();
        // Ordem por id (insercao), nao a ordem natural de leitura do SQLite --
        // o indice unico da migration 2026_09_30_000001 muda o plano de
        // consulta pra ordenar por ip_cidr, nao por rowid, quebrando qualquer
        // suposicao implicita de ordem aqui.
        $this->assertSame(['203.0.113.10' => 'active', '2001:db8::10' => 'active'], $servidor->allowedIps()->orderBy('id')->pluck('status', 'ip_cidr')->all());
        $this->assertTrue($servidor->ipAllowed('203.0.113.10'));
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'servidor.ips.added')->where('target_id', $servidor->id)->count());
        $this->assertSame(0, \App\Models\AuditLog::where('action', 'servidor.ip_pendente')->count());
    }

    public function test_admin_editing_a_server_never_changes_the_acl(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create(['ip_v4' => null]);

        $this->actingAs($admin)->put(route('servidores.update', $servidor), $this->payload([
            'empresa_id' => $servidor->empresa_id, 'ip_v4' => '203.0.113.55',
        ]))->assertRedirect();

        $this->assertSame(0, $servidor->allowedIps()->count(), 'Na edição o IP é só informativo.');
    }
}
