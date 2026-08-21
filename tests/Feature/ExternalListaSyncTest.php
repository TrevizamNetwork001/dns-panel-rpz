<?php

namespace Tests\Feature;

use App\Models\Lista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalListaSyncTest extends TestCase
{
    use RefreshDatabase;

    private function hostfile(array $domains): string
    {
        $lines = ["# comentario que deve ser ignorado", ''];
        foreach ($domains as $domain) {
            $lines[] = "127.0.0.1\t{$domain}";
        }

        return implode("\n", $lines);
    }

    private function plainfile(array $domains): string
    {
        return "# comentario\n" . implode("\n", $domains);
    }

    private function manyDomains(int $count, string $prefix = 'malware'): array
    {
        $domains = [];
        for ($i = 0; $i < $count; $i++) {
            $domains[] = "{$prefix}{$i}.example";
        }

        return $domains;
    }

    public function test_syncs_all_active_external_listas(): void
    {
        $listaA = Lista::factory()->externa('feed-a', 'https://feed-a.example/hosts.txt', 'hostfile')->create(['nome' => 'Feed A']);
        $listaB = Lista::factory()->externa('feed-b', 'https://feed-b.example/plain.txt', 'plain')->create(['nome' => 'Feed B']);

        Http::fake([
            'feed-a.example/*' => Http::response($this->hostfile($this->manyDomains(150, 'a')), 200),
            'feed-b.example/*' => Http::response($this->plainfile($this->manyDomains(150, 'b')), 200),
        ]);

        $this->artisan('external:sync')->assertSuccessful();

        $this->assertSame(150, $listaA->fresh()->dominios()->where('ativo', true)->count());
        $this->assertSame(150, $listaB->fresh()->dominios()->where('ativo', true)->count());
        $this->assertTrue($listaA->fresh()->dominios()->where('dominio', 'a0.example')->exists());
        $this->assertTrue($listaB->fresh()->dominios()->where('dominio', 'b0.example')->exists());
    }

    public function test_second_sync_deactivates_domains_no_longer_in_feed(): void
    {
        $lista = Lista::factory()->externa('feed', 'https://feed.example/hosts.txt')->create();

        Http::fakeSequence('feed.example/*')
            ->push($this->hostfile($this->manyDomains(150)), 200)
            ->push($this->hostfile($this->manyDomains(150, 'novo')), 200);

        $this->artisan('external:sync');
        $this->artisan('external:sync');

        $lista->refresh();
        $this->assertSame(150, $lista->dominios()->where('ativo', true)->count());
        $this->assertFalse($lista->dominios()->where('dominio', 'malware0.example')->where('ativo', true)->exists());
        $this->assertTrue($lista->dominios()->where('dominio', 'novo0.example')->where('ativo', true)->exists());
    }

    public function test_returns_failure_but_continues_when_one_feed_is_broken(): void
    {
        $listaOk = Lista::factory()->externa('feed-ok', 'https://feed-ok.example/hosts.txt')->create();
        $listaQuebrada = Lista::factory()->externa('feed-quebrado', 'https://feed-quebrado.example/hosts.txt')->create();

        Http::fake([
            'feed-ok.example/*' => Http::response($this->hostfile($this->manyDomains(150)), 200),
            'feed-quebrado.example/*' => Http::response($this->hostfile(['so-um.example']), 200),
        ]);

        $this->artisan('external:sync')->assertFailed();

        $this->assertSame(150, $listaOk->fresh()->dominios()->where('ativo', true)->count());
        $this->assertSame(0, $listaQuebrada->fresh()->dominios()->count(), 'lista com feed quebrado nao deveria ter recebido nada');
    }

    public function test_skips_sync_when_paused(): void
    {
        Lista::factory()->externa('feed', 'https://feed.example/hosts.txt')->create(['sync_ativo' => false]);

        Http::fake(['feed.example/*' => Http::response($this->hostfile($this->manyDomains(150)), 200)]);

        $this->artisan('external:sync')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('dominios', 0);
    }

    public function test_ignores_manual_listas(): void
    {
        Lista::factory()->create(['nome' => 'Lista manual']);

        $this->artisan('external:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_ignores_malformed_lines_and_localhost(): void
    {
        $lista = Lista::factory()->externa('feed', 'https://feed.example/hosts.txt')->create();

        $body = "# comentario\n127.0.0.1 localhost\nlinha sem colunas\n" . $this->hostfile($this->manyDomains(150));

        Http::fake(['feed.example/*' => Http::response($body, 200)]);

        $this->artisan('external:sync');

        $lista->refresh();
        $this->assertFalse($lista->dominios()->where('dominio', 'localhost')->exists());
        $this->assertSame(150, $lista->dominios()->count());
    }

    public function test_plain_format_parses_one_domain_per_line(): void
    {
        $lista = Lista::factory()->externa('feed', 'https://feed.example/plain.txt', 'plain')->create();

        Http::fake(['feed.example/*' => Http::response($this->plainfile($this->manyDomains(150)), 200)]);

        $this->artisan('external:sync');

        $lista->refresh();
        $this->assertSame(150, $lista->dominios()->where('ativo', true)->count());
    }
}
