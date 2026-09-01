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

class RpzEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_valid_unique_and_stable_after_company_rename(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create(['nome' => 'SP Fiber Telecom', 'rpz_slug' => null]);
        $this->assertSame('sp-fiber-telecom', $empresa->rpz_slug);

        $this->actingAs($admin)->put(route('empresas.update', $empresa), [
            'nome' => 'SP Fiber Telecom Ltda', 'rpz_slug' => $empresa->rpz_slug,
            'documento' => $empresa->documento, 'email_contato' => $empresa->email_contato, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('sp-fiber-telecom', $empresa->fresh()->rpz_slug);

        Empresa::factory()->create(['rpz_slug' => 'slug-unico']);
        $this->actingAs($admin)->post(route('empresas.store'), [
            'nome' => 'Inválida', 'rpz_slug' => '../slug-unico', 'status' => 'active',
        ])->assertSessionHasErrors('rpz_slug');
    }

    public function test_generated_slug_collision_gets_predictable_suffix(): void
    {
        $first = Empresa::factory()->create(['nome' => 'SP Fiber', 'rpz_slug' => null]);
        $second = Empresa::factory()->create(['nome' => 'SP Fiber', 'rpz_slug' => null]);
        $this->assertSame('sp-fiber', $first->rpz_slug);
        $this->assertSame('sp-fiber-2', $second->rpz_slug);
    }

    public function test_authorized_ipv4_and_ipv4_cidr_receive_streamed_zone(): void
    {
        [$empresa] = $this->endpoint('spfiber', ['127.0.0.1/32']);
        $response = $this->get("/rpz/{$empresa->rpz_slug}.zone");
        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('CNAME .', $response->streamedContent());
    }

    public function test_disallowed_ipv4_and_company_without_acl_receive_403(): void
    {
        [$empresa] = $this->endpoint('spfiber', ['203.0.113.0/24']);
        $this->get("/rpz/{$empresa->rpz_slug}.zone")->assertForbidden();
        [$withoutAcl] = $this->endpoint('sem-acl');
        $this->get("/rpz/{$withoutAcl->rpz_slug}.zone")->assertForbidden();
    }

    public function test_authorized_ipv6_individual_and_cidr_are_supported(): void
    {
        [$empresa] = $this->endpoint('ipv6', ['2001:db8::1', '2804:4ff0::/64']);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:0db8:0:0:0:0:0:1'])
            ->get("/rpz/{$empresa->rpz_slug}.zone")->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '2804:4ff0::249'])
            ->get("/rpz/{$empresa->rpz_slug}.zone")->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '2804:4ff1::249'])
            ->get("/rpz/{$empresa->rpz_slug}.zone")->assertForbidden();
    }

    public function test_untrusted_forwarded_headers_cannot_spoof_source_ip(): void
    {
        [$empresa] = $this->endpoint('proxy-safe', ['203.0.113.10']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.10', 'X-Real-IP' => '203.0.113.10'])
            ->get("/rpz/{$empresa->rpz_slug}.zone")->assertForbidden();
    }

    public function test_missing_or_inactive_company_receives_404(): void
    {
        $this->get('/rpz/empresa-inexistente.zone')->assertNotFound();
        [$empresa] = $this->endpoint('inativa', ['127.0.0.1']);
        $empresa->update(['status' => 'inactive']);
        $this->get('/rpz/inativa.zone')->assertNotFound();
    }

    public function test_company_zone_aggregates_active_lists_deduplicates_and_respects_exclusions(): void
    {
        [$empresa, $servidor] = $this->endpoint('agregada', ['127.0.0.1']);
        $one = Lista::factory()->create(['status' => 'active']);
        $two = Lista::factory()->create(['status' => 'active']);
        $inactive = Lista::factory()->inactive()->create();
        $servidor->listas()->attach([$one->id, $two->id, $inactive->id]);
        Dominio::factory()->for($one)->create(['dominio' => 'duplicado.example']);
        Dominio::factory()->for($two)->create(['dominio' => 'DUPLICADO.EXAMPLE']);
        Dominio::factory()->for($two)->inativo()->create(['dominio' => 'excluido.example', 'inactive_reason' => 'anatel_exclusion']);
        Dominio::factory()->for($inactive)->create(['dominio' => 'lista-inativa.example']);

        $content = $this->get("/rpz/{$empresa->rpz_slug}.zone")->streamedContent();
        $this->assertSame(1, preg_match_all('/^duplicado\.example CNAME \.$/m', $content));
        $this->assertStringContainsString('*.duplicado.example CNAME .', $content);
        $this->assertStringNotContainsString('excluido.example', $content);
        $this->assertStringNotContainsString('lista-inativa.example', $content);
    }

    public function test_legacy_token_endpoint_keeps_working(): void
    {
        [, $servidor] = $this->endpoint('legado');
        $this->get("/rpz/{$servidor->token}.zone")->assertOk();
    }

    public function test_admin_sees_short_url_acl_sources_and_unbound_configuration_but_client_does_not_see_admin_panel(): void
    {
        [$empresa, $servidor] = $this->endpoint('spfiber', ['127.0.0.1/32']);
        $lista = Lista::factory()->create(['nome' => 'ANATEL']);
        $servidor->listas()->attach($lista);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('empresas.show', $empresa))->assertOk()
            ->assertSee('/rpz/spfiber.zone')->assertSee('ACL por IP')->assertSee('127.0.0.1/32')
            ->assertSee('ANATEL')->assertSee('zonefile:')->assertSee('rpz-log-name:');

        $client = User::factory()->cliente($empresa)->create();
        $this->actingAs($client)->get(route('empresas.show', $empresa))->assertDontSee('company-rpz-access');
    }

    private function endpoint(string $slug, array $rules = []): array
    {
        $empresa = Empresa::factory()->create(['rpz_slug' => $slug]);
        $servidor = Servidor::factory()->for($empresa)->create(['status' => 'active']);
        foreach ($rules as $rule) {
            ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => $rule, 'status' => 'active']);
        }

        return [$empresa, $servidor];
    }
}
