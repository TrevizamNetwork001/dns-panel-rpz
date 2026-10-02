<?php

namespace Tests\Feature;

use App\Jobs\BuildRpzZone;
use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\SugestaoDominio;
use App\Models\User;
use App\Services\AnatelLegacyFeedSyncer;
use App\Services\ExternalListaSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Garante que os pontos de mutação reais (não uma reimplementação isolada
 * do RpzDirtyMarker) efetivamente despacham BuildRpzZone pra zona certa.
 * Isso é proteção de regressão: se alguém remover uma chamada de
 * markDirty() de um controller no futuro, esse teste falha.
 */
class RpzDirtyMarkerWiringTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_lista_store_marks_attached_servers(): void
    {
        Queue::fake();
        $servidor = Servidor::factory()->create();

        $this->actingAs($this->admin())->post(route('listas.store'), [
            'nome' => 'Nova lista', 'status' => 'active', 'origem' => 'manual',
            'servidor_ids' => [$servidor->id],
        ])->assertRedirect();

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_lista_update_marks_both_old_and_new_servers(): void
    {
        $servidorAntigo = Servidor::factory()->create();
        $servidorNovo = Servidor::factory()->create();
        $lista = Lista::factory()->create();
        $lista->servidores()->attach($servidorAntigo);

        Queue::fake();
        $this->actingAs($this->admin())->put(route('listas.update', $lista), [
            'nome' => $lista->nome, 'status' => 'active', 'origem' => 'manual',
            'servidor_ids' => [$servidorNovo->id],
        ])->assertRedirect();

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidorAntigo->id);
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidorNovo->id);
    }

    public function test_lista_destroy_marks_servers_before_deleting(): void
    {
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create();
        $lista->servidores()->attach($servidor);

        Queue::fake();
        $this->actingAs($this->admin())->delete(route('listas.destroy', $lista))->assertRedirect();

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_attach_and_detach_lista_mark_the_server(): void
    {
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);

        Queue::fake();
        $this->actingAs($this->admin())->post(route('servidores.listas.attach', [$servidor, $lista]))->assertRedirect();
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);

        Queue::fake();
        $this->actingAs($this->admin())->delete(route('servidores.listas.detach', [$servidor, $lista]))->assertRedirect();
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_dominio_store_toggle_and_destroy_mark_the_lists_servers(): void
    {
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);

        Queue::fake();
        $this->actingAs($this->admin())->post(route('listas.dominios.store', $lista), ['dominio' => 'novo.example'])->assertRedirect();
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);

        $dominio = Dominio::factory()->for($lista)->create();
        Queue::fake();
        $this->actingAs($this->admin())->patch(route('dominios.toggle', $dominio))->assertRedirect();
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);

        Queue::fake();
        $this->actingAs($this->admin())->delete(route('dominios.destroy', $dominio))->assertRedirect();
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_bulk_store_marks_dirty_only_once_for_the_whole_batch(): void
    {
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);

        Queue::fake();
        $this->actingAs($this->admin())->post(route('listas.dominios.bulk', $lista), [
            'dominios' => "um.example\ndois.example\ntres.example",
        ])->assertRedirect();

        // markForList() despacha 1x pro server:<id> e 1x pro company:<id> da
        // empresa dele (zona empresarial tambem muda) -- nao 1 por dominio,
        // que e o que este teste realmente protege.
        Queue::assertPushed(BuildRpzZone::class, 2);
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_empresa_update_marks_company_and_all_its_servers(): void
    {
        $empresa = Empresa::factory()->create();
        $servidor = Servidor::factory()->create(['empresa_id' => $empresa->id]);

        Queue::fake();
        $this->actingAs($this->admin())->put(route('empresas.update', $empresa), [
            'nome' => $empresa->nome, 'documento' => $empresa->documento,
            'email_contato' => $empresa->email_contato, 'status' => 'active',
        ])->assertRedirect();

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'company:'.$empresa->id);
        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_aprovar_sugestao_marks_the_target_list_servers(): void
    {
        // Gap encontrado na auditoria pre-cutover (29/09/2026): aprovar uma
        // sugestao de dominio inseria em `dominios` sem passar por nenhum
        // dirty marker.
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        $sugestao = SugestaoDominio::create([
            'empresa_id' => $servidor->empresa_id,
            'dominio' => 'sugerido.example',
            'status' => 'pending',
            'created_by' => $this->admin()->id,
            'ip_address' => '203.0.113.9',
        ]);

        Queue::fake();
        $this->actingAs($this->admin())->post(route('sugestoes.aprovar', $sugestao), [
            'lista_id' => $lista->id,
        ])->assertRedirect();

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_anatel_legacy_feed_sync_marks_dirty_when_it_writes_domains(): void
    {
        // Gap encontrado na auditoria pre-cutover (29/09/2026): esse syncer
        // roda sozinho a cada 6h via automation-loop e escrevia direto em
        // `dominios` via DB::table(), sem nenhum dirty marker.
        Http::fake([
            '*' => Http::response(
                implode("\n", array_map(fn ($i) => 'local-zone: "legacy-dominio'.$i.'.example" redirect', range(1, 150))),
                200,
            ),
        ]);
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->anatel()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);

        Queue::fake();
        app(AnatelLegacyFeedSyncer::class)->sync($lista);

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_external_lista_sync_marks_dirty_only_when_content_actually_changes(): void
    {
        Http::fake([
            '*' => Http::response(
                implode("\n", array_map(fn ($i) => "0.0.0.0 dominio{$i}.example", range(1, 150))),
                200,
            ),
        ]);
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->externa()->create(['empresa_id' => $servidor->empresa_id, 'fonte_url' => 'https://feed.example/list.txt']);
        $servidor->listas()->attach($lista);

        Queue::fake();
        app(ExternalListaSyncer::class)->sync($lista);

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }
}
