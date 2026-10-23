<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\EmpresaDomainException;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Models\User;
use App\Services\RpzExceptionPolicy;
use App\Services\RpzZoneBuilder;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyDomainExceptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://policy.test', 'rpz.publication_enabled' => true, 'rpz.notify_enabled' => false]);
        Queue::fake();
        Storage::fake('local');
    }

    private function sharedSource(): array
    {
        $a = Servidor::factory()->create();
        $b = Servidor::factory()->create();
        $list = Lista::factory()->create(['empresa_id' => null]);
        $a->listas()->attach($list);
        $b->listas()->attach($list);
        foreach (['example.com', 'ads.example.com', 'www.example.com', 'com.br'] as $domain) {
            Dominio::factory()->for($list)->create(['dominio' => $domain]);
        }

        return [$a, $b, $list];
    }

    public function test_company_exceptions_do_not_change_shared_sources_or_another_company(): void
    {
        [$a, $b, $list] = $this->sharedSource();
        EmpresaDomainException::create(['empresa_id' => $a->empresa_id, 'dominio' => 'example.com']);
        $builder = app(RpzZoneBuilder::class);
        $zone = $builder->build(clone $builder->serverQuery($a->id));
        $this->assertStringContainsString('example.com CNAME rpz-passthru.', $zone);
        $this->assertStringNotContainsString('ads.example.com CNAME .', $zone);
        $this->assertStringContainsString('example.com CNAME .', $builder->build($builder->serverQuery($b->id)));
        $this->assertStringContainsString('example.com CNAME .', $builder->build($builder->listQuery($list)));
        $this->assertSame(4, $list->dominios()->where('ativo', true)->count());
        $this->assertStringContainsString('example.com CNAME rpz-passthru.', $builder->build($builder->companyQuery($a->empresa_id)));
    }

    public function test_deep_exception_preserves_wildcard_blocks_for_ancestors_and_siblings(): void
    {
        [$a] = $this->sharedSource();
        EmpresaDomainException::create(['empresa_id' => $a->empresa_id, 'dominio' => 'allow.deep.example.com']);
        $builder = app(RpzZoneBuilder::class);
        $zone = $builder->build($builder->serverQuery($a->id));
        $this->assertStringContainsString("deep.example.com CNAME .\n", $zone);
        $this->assertStringContainsString('*.deep.example.com CNAME .', $zone);
        $this->assertStringContainsString('allow.deep.example.com CNAME rpz-passthru.', $zone);
        $this->assertStringContainsString('*.allow.deep.example.com CNAME rpz-passthru.', $zone);
    }

    public function test_inherited_block_coverage_is_restored_at_empty_nodes_but_wpad_alone_is_not_expanded(): void
    {
        [$a, , $list] = $this->sharedSource();
        Dominio::factory()->for($list)->create(['dominio' => 'blocked.deep.example.com']);
        Dominio::factory()->for($list)->create(['dominio' => 'wpad.domain.name']);
        EmpresaDomainException::create(['empresa_id' => $a->empresa_id, 'dominio' => 'allow.deep.example.com']);
        EmpresaDomainException::create(['empresa_id' => $a->empresa_id, 'dominio' => 'allow.deep.wpad.domain.name']);
        $builder = app(RpzZoneBuilder::class);
        $zone = $builder->build($builder->serverQuery($a->id));
        $this->assertStringContainsString("\ndeep.example.com CNAME .\n", $zone);
        $this->assertStringContainsString('*.deep.example.com CNAME .', $zone);
        $this->assertStringNotContainsString('deep.wpad.domain.name CNAME .', $zone);
    }

    public function test_www_exception_preserves_the_parent_and_filters_hosts_only_for_its_subtree(): void
    {
        [$a] = $this->sharedSource();
        EmpresaDomainException::create(['empresa_id' => $a->empresa_id, 'dominio' => 'www.example.com']);
        $builder = app(RpzZoneBuilder::class);
        $zone = $builder->build($builder->serverQuery($a->id));
        $this->assertStringContainsString('www.example.com CNAME rpz-passthru.', $zone);
        $this->assertStringContainsString("\nexample.com CNAME .\n", $zone);
        $hosts = implode("\n", iterator_to_array($builder->hostsLines($builder->serverQuery($a->id))));
        $this->assertStringContainsString('0.0.0.0 example.com', $hosts);
        $this->assertSame('www.example.com', RpzExceptionPolicy::normalize(' WWW.Example.COM. '));
    }

    public function test_admin_can_manage_exceptions_with_audit_and_durable_invalidation(): void
    {
        [$a, $b] = $this->sharedSource();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('empresas.exceptions.store', $a->empresa_id), ['dominio' => 'WWW.Example.COM.', 'motivo' => 'Serviço necessário'])->assertSessionHasNoErrors()->assertRedirect();
        $exception = EmpresaDomainException::sole();
        $this->assertSame('www.example.com', $exception->dominio);
        $this->assertTrue(RpzZone::where('zone_key', 'server:'.$a->id)->firstOrFail()->needsRebuild());
        $this->assertTrue(RpzZone::where('zone_key', 'company:'.$a->empresa_id)->firstOrFail()->needsRebuild());
        $this->assertNull(RpzZone::where('zone_key', 'server:'.$b->id)->first());
        $this->assertDatabaseHas('audit_logs', ['action' => 'empresa.exception.created']);
        $this->get(route('empresas.exceptions.index', $a->empresa_id))->assertOk()->assertSee('www.example.com');
        $this->delete(route('empresas.exceptions.destroy', [$b->empresa_id, $exception]))->assertNotFound();
        $this->assertDatabaseCount('empresa_domain_exceptions', 1);
        $this->delete(route('empresas.exceptions.destroy', [$a->empresa_id, $exception]))->assertRedirect();
        $this->assertDatabaseCount('empresa_domain_exceptions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'empresa.exception.deleted']);
    }

    public function test_clients_cannot_read_or_modify_company_exceptions(): void
    {
        [$a] = $this->sharedSource();
        $client = User::factory()->create(['role' => 'cliente', 'empresa_id' => $a->empresa_id]);
        $exception = EmpresaDomainException::create(['empresa_id' => $a->empresa_id, 'dominio' => 'example.com']);
        $this->actingAs($client)->get(route('empresas.exceptions.index', $a->empresa_id))->assertForbidden();
        $this->post(route('empresas.exceptions.store', $a->empresa_id), ['dominio' => 'other.example.com'])->assertForbidden();
        $this->delete(route('empresas.exceptions.destroy', [$a->empresa_id, $exception]))->assertForbidden();
    }

    public static function invalidDomains(): array
    {
        return array_map(fn ($domain) => [$domain], ['https://example.com', '*.example.com', '127.0.0.1', 'x CNAME .', 'policy.test', 'blocktest.policy.test', str_repeat('a', 64).'.com', str_repeat('a', 60).'.'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.'.str_repeat('d', 60).'.com']);
    }

    #[DataProvider('invalidDomains')]
    public function test_invalid_or_canary_exceptions_are_rejected(string $domain): void
    {
        $company = Empresa::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('empresas.exceptions.store', $company), ['dominio' => $domain])->assertSessionHasErrors('dominio');
        $this->assertDatabaseCount('empresa_domain_exceptions', 0);
    }

    public function test_normalized_duplicates_are_rejected(): void
    {
        $company = Empresa::factory()->create();
        DB::table('empresa_domain_exceptions')->insert(['empresa_id' => $company->id, 'dominio' => 'EXAMPLE.COM.']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('empresas.exceptions.store', $company), ['dominio' => 'example.com'])->assertSessionHasErrors('dominio');
        $this->assertDatabaseCount('empresa_domain_exceptions', 1);
    }

    public function test_adding_and_removing_an_exception_changes_hash_and_monotonic_version_only_for_its_company(): void
    {
        [$a, $b] = $this->sharedSource();
        $service = app(RpzZoneBuildService::class);
        $service->build('server:'.$a->id);
        $service->build('server:'.$b->id);
        $before = RpzZone::where('zone_key', 'server:'.$a->id)->firstOrFail();
        $other = RpzZone::where('zone_key', 'server:'.$b->id)->firstOrFail();
        $exception = EmpresaDomainException::create(['empresa_id' => $a->empresa_id, 'dominio' => 'example.com']);
        $this->assertSame('published', $service->build('server:'.$a->id)['status']);
        $after = $before->fresh();
        $this->assertNotSame($before->content_hash, $after->content_hash);
        $this->assertSame($before->revision + 1, $after->revision);
        $this->assertSame($other->content_hash, $other->fresh()->content_hash);
        $exception->delete();
        $service->build('server:'.$a->id);
        $this->assertSame($before->content_hash, $before->fresh()->content_hash);
        $this->assertSame($before->revision + 2, $before->fresh()->revision);
    }
}
