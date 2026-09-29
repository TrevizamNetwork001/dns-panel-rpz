<?php

namespace Tests\Feature;

use App\Jobs\BuildRpzZone;
use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
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
