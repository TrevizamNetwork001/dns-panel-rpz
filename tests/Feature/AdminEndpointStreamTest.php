<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminEndpointStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_formats_real_events_without_changing_source_data(): void
    {
        $admin = User::factory()->admin()->create();
        $server = Servidor::factory()->create();
        $list = Lista::factory()->create(['nome' => 'Fonte 1007']);
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->count(1007)->create(['ativo' => false]);
        $sync = $server->syncLogs()->create(['dominios_count' => 244493, 'ip_address' => '2804:4ff0::249', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get(route('servidores.show', $server));
        $response->assertOk()->assertSee('endpoint-event-stream')
            ->assertSee('Adição')->assertSee('Remoção')->assertSee('Sincronização')
            ->assertSee('Fonte 1007')->assertSee('ganhou 1.007 domínios')->assertSee('perdeu 1.007 domínios')
            ->assertSee('Servidor sincronizou — 244.493 domínios entregues')
            ->assertSee('IP 2804:4ff0::249')->assertDontSee('Fonte +')->assertDontSee('Fonte -');
        $events = collect($response->viewData('logServidor'));
        $this->assertEqualsCanonicalizing(['sync', 'lista_add', 'lista_remove'], $events->pluck('tipo')->all());
        $this->assertSame('Servidor sincronizou — 244493 domínios entregues', $events->firstWhere('tipo', 'sync')['detalhe']);
        $this->assertSame(244493, $sync->fresh()->dominios_count);
        $this->assertSame(1007, $list->dominios()->where('ativo', false)->count());
    }

    public function test_stream_preserves_eighty_event_limit_order_and_constant_query_count(): void
    {
        $admin = User::factory()->admin()->create();
        $server = Servidor::factory()->create(['created_at' => now()->subDays(5)]);
        $server->listas()->attach(Lista::factory()->create());
        $this->actingAs($admin);
        DB::enableQueryLog();
        $this->get(route('servidores.show', $server))->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();
        $server->listas()->attach(Lista::factory()->count(10)->create());
        foreach (range(1, 85) as $i) {
            $server->syncLogs()->create(['dominios_count' => $i, 'created_at' => now()->subMinutes($i)]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('servidores.show', $server));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $response->assertOk();
        $this->assertSame($baseline, $queries, 'Mais fontes e eventos não devem adicionar queries.');
        $this->assertSame(80, substr_count($response->getContent(), '<li class="endpoint-event '));
        $events = $response->viewData('logServidor');
        $this->assertCount(80, $events);
        $this->assertSame('Servidor sincronizou — 1 domínios entregues', $events[0]['detalhe']);
        $this->assertSame('Servidor sincronizou — 80 domínios entregues', $events[79]['detalhe']);
    }

    public function test_client_keeps_original_log_and_admin_has_no_legacy_panel(): void
    {
        $server = Servidor::factory()->create();
        $client = User::factory()->cliente($server->empresa)->create();
        $server->syncLogs()->create(['dominios_count' => 244493, 'created_at' => now()]);
        $this->actingAs($client)->get(route('servidores.show', $server))->assertOk()
            ->assertDontSee('endpoint-event-stream')->assertDontSee('admin-endpoint-detail')
            ->assertSee('Log do servidor (últimos 30 dias)')->assertSee('244493 domínios entregues')
            ->assertSee('Cole este bloco no');
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('servidores.show', $server));
        $response->assertOk()->assertSee('Status cadastral')->assertSee('Estado operacional');
        $response->assertDontSee('legacy-token-access')->assertDontSee('Acesso legado por token');
        $this->assertSame(1, substr_count($response->getContent(), 'Última consulta'));
    }
}
