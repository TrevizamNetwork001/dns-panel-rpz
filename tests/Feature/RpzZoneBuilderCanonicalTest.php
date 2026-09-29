<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Services\RpzZoneBuilder;
use App\Services\RpzZoneVersioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RpzZoneBuilderCanonicalTest extends TestCase
{
    use RefreshDatabase;

    private function readStream(array $canonical): string
    {
        rewind($canonical['stream']);
        $conteudo = stream_get_contents($canonical['stream']);
        fclose($canonical['stream']);

        return $conteudo === false ? '' : $conteudo;
    }

    public function test_wpad_gets_only_exact_rule_no_wildcard(): void
    {
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'wpad.domain.name']);
        $builder = app(RpzZoneBuilder::class);

        $canonical = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');

        $this->assertSame("wpad.domain.name CNAME .\n", $this->readStream($canonical));
        $this->assertSame(1, $canonical['rule_count']);
    }

    public function test_owner_too_long_is_excluded_and_counted(): void
    {
        $lista = Lista::factory()->create();
        // Dominio valido isoladamente (198 chars, labels <=63) que so estoura o
        // limite de 253 do owner quando somado a uma zona longa.
        $longo = str_repeat('a', 60).'.'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.'.str_repeat('d', 15);
        Dominio::factory()->for($lista)->create(['dominio' => $longo]);
        Dominio::factory()->for($lista)->create(['dominio' => 'curto.example']);
        $builder = app(RpzZoneBuilder::class);

        $canonical = $builder->buildCanonical($builder->listQuery($lista), str_repeat('z', 200).'.test', '.');
        fclose($canonical['stream']);

        $this->assertSame(1, $canonical['excluded_count']);
        $this->assertSame(2, $canonical['domain_count']);
    }

    public function test_content_hash_changes_when_target_changes(): void
    {
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'exemplo.example']);
        $builder = app(RpzZoneBuilder::class);

        $nxdomain = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');
        $redirect = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', 'painel.test.');
        fclose($nxdomain['stream']);
        fclose($redirect['stream']);

        $this->assertNotSame($nxdomain['content_hash'], $redirect['content_hash']);
    }

    public function test_content_hash_is_stable_for_same_effective_set(): void
    {
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'estavel.example']);
        $builder = app(RpzZoneBuilder::class);

        $c1 = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');
        $c2 = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');
        fclose($c1['stream']);
        fclose($c2['stream']);

        $this->assertSame($c1['content_hash'], $c2['content_hash']);
    }

    public function test_content_hash_changes_when_a_rule_is_added(): void
    {
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'a.example']);
        $builder = app(RpzZoneBuilder::class);

        $antes = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');
        fclose($antes['stream']);

        Dominio::factory()->for($lista)->create(['dominio' => 'b.example']);
        $depois = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');
        fclose($depois['stream']);

        $this->assertNotSame($antes['content_hash'], $depois['content_hash']);
    }

    public function test_next_serial_blocks_at_32_bit_limit_instead_of_wrapping(): void
    {
        $versioner = app(RpzZoneVersioner::class);
        $this->assertSame(RpzZoneVersioner::SERIAL_MAX, $versioner->nextSerial(RpzZoneVersioner::SERIAL_MAX - 1));

        $this->expectException(\RuntimeException::class);
        $versioner->nextSerial(RpzZoneVersioner::SERIAL_MAX);
    }

    public function test_render_zone_uses_precomputed_stream_without_requerying(): void
    {
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'render.example']);
        $builder = app(RpzZoneBuilder::class);
        $canonical = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');

        // Domínio novo criado DEPOIS do buildCanonical não deve aparecer no
        // render, porque o stream já foi totalmente escrito, não requeria.
        Dominio::factory()->for($lista)->create(['dominio' => 'tarde-demais.example']);

        $zone = implode('', iterator_to_array($builder->renderZone($canonical, 'painel.test', '123', '.'), false));
        fclose($canonical['stream']);

        $this->assertStringContainsString('render.example CNAME .', $zone);
        $this->assertStringNotContainsString('tarde-demais.example', $zone);
    }

    public function test_stream_is_seekable_and_survives_past_the_in_memory_threshold(): void
    {
        // Gera mais dados do que CANONICAL_STREAM_MEMORY_THRESHOLD (2MB) pra forcar
        // o spill real pro arquivo temporario em disco, e confirma que o conteudo
        // ainda sai correto depois do rewind (não é so um teste "cabe na RAM").
        // Dominios com sufixo longo pra cruzar o limiar com poucas linhas -- o
        // comportamento em escala real (~760 mil regras) ja foi validado à parte
        // com dados reais de produção (benchmark/byte-parity), fora do PHPUnit;
        // aqui só precisa provar que o código do spill em si funciona, com o
        // menor footprint de memória possível pro processo compartilhado do
        // PHPUnit (evita empurrar outros testes sensíveis a memory_limit).
        $lista = Lista::factory()->create();
        $now = now();
        // Label extra de 50 chars (valido, <=63) só pra engordar cada linha sem
        // esbarrar no limite de tamanho de label/owner do DomainNormalizer.
        $labelExtra = str_repeat('a', 50);
        $total = 18000; // total * (~160 bytes/regra com wildcard) > 2MB
        $linhas = [];
        for ($i = 0; $i < $total; $i++) {
            $linhas[] = ['lista_id' => $lista->id, 'dominio' => sprintf('spill-%06d.%s.example', $i, $labelExtra), 'ativo' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($linhas, 2000) as $lote) {
            DB::table('dominios')->insert($lote);
        }
        unset($linhas);

        $builder = app(RpzZoneBuilder::class);
        $canonical = $builder->buildCanonical($builder->listQuery($lista), 'painel.test', '.');

        $this->assertSame($total, $canonical['domain_count']);
        $this->assertSame($total * 2, $canonical['rule_count']);

        $conteudo = $this->readStream($canonical);
        $this->assertStringContainsString('spill-000000.'.$labelExtra.'.example CNAME .', $conteudo);
        $this->assertStringContainsString('spill-017999.'.$labelExtra.'.example CNAME .', $conteudo);
        $this->assertStringContainsString('*.spill-017999.'.$labelExtra.'.example CNAME .', $conteudo);
        $this->assertSame($total * 2, substr_count($conteudo, "\n"));
        $this->assertGreaterThan(RpzZoneBuilder::CANONICAL_STREAM_MEMORY_THRESHOLD, strlen($conteudo));

        unset($conteudo);
        gc_collect_cycles();
    }
}
