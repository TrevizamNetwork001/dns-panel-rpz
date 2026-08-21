<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlhausSyncTest extends TestCase
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

    private function manyDomains(int $count, string $prefix = 'malware'): array
    {
        $domains = [];
        for ($i = 0; $i < $count; $i++) {
            $domains[] = "{$prefix}{$i}.example";
        }

        return $domains;
    }

    public function test_creates_external_lista_and_imports_domains(): void
    {
        $domains = $this->manyDomains(150);
        Http::fake([
            'urlhaus.abuse.ch/*' => Http::response($this->hostfile($domains), 200),
        ]);

        $this->artisan('urlhaus:sync')->assertSuccessful();

        $lista = Lista::where('fonte_externa', 'urlhaus')->first();
        $this->assertNotNull($lista);
        $this->assertSame('externa', $lista->origem);
        $this->assertNotNull($lista->last_sync_at);
        $this->assertSame(150, $lista->dominios()->where('ativo', true)->count());
        $this->assertTrue($lista->dominios()->where('dominio', 'malware0.example')->exists());
    }

    public function test_second_sync_deactivates_domains_no_longer_in_feed(): void
    {
        Http::fakeSequence('urlhaus.abuse.ch/*')
            ->push($this->hostfile($this->manyDomains(150)), 200)
            ->push($this->hostfile($this->manyDomains(150, 'novo')), 200);

        $this->artisan('urlhaus:sync');
        $this->artisan('urlhaus:sync');

        $lista = Lista::where('fonte_externa', 'urlhaus')->first();
        $this->assertSame(150, $lista->dominios()->where('ativo', true)->count());
        $this->assertFalse($lista->dominios()->where('dominio', 'malware0.example')->where('ativo', true)->exists());
        $this->assertTrue($lista->dominios()->where('dominio', 'novo0.example')->where('ativo', true)->exists());
    }

    public function test_aborts_when_feed_returns_too_few_domains(): void
    {
        Http::fakeSequence('urlhaus.abuse.ch/*')
            ->push($this->hostfile($this->manyDomains(150)), 200)
            ->push($this->hostfile(['so-um.example']), 200);

        $this->artisan('urlhaus:sync');
        $this->artisan('urlhaus:sync')->assertFailed();

        $lista = Lista::where('fonte_externa', 'urlhaus')->first();
        $this->assertSame(150, $lista->dominios()->where('ativo', true)->count(), 'lista nao deveria ter sido esvaziada por um feed suspeito');
    }

    public function test_skips_sync_when_paused(): void
    {
        Lista::factory()->externa()->create([
            'nome' => 'URLhaus — Malware & Phishing (auto)',
            'fonte_externa' => 'urlhaus',
            'sync_ativo' => false,
        ]);

        Http::fake([
            'urlhaus.abuse.ch/*' => Http::response($this->hostfile($this->manyDomains(150)), 200),
        ]);

        $this->artisan('urlhaus:sync')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('dominios', 0);
    }

    public function test_ignores_malformed_lines_and_localhost(): void
    {
        $body = "# comentario\n127.0.0.1 localhost\nlinha sem colunas\n" . $this->hostfile($this->manyDomains(150));

        Http::fake(['urlhaus.abuse.ch/*' => Http::response($body, 200)]);

        $this->artisan('urlhaus:sync');

        $lista = Lista::where('fonte_externa', 'urlhaus')->first();
        $this->assertFalse($lista->dominios()->where('dominio', 'localhost')->exists());
        $this->assertSame(150, $lista->dominios()->count());
    }
}
