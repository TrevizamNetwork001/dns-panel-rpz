<?php

namespace Tests\Feature;

use App\Models\Lista;
use App\Models\SugestaoDominio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSuggestionsPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function suggestion(User $client, string $status = 'pending'): SugestaoDominio
    {
        return SugestaoDominio::create([
            'empresa_id' => $client->empresa_id, 'created_by' => $client->id,
            'dominio' => $status.'.example', 'motivo' => 'site malicioso', 'status' => $status,
        ]);
    }

    public function test_pending_menu_and_dialogs_use_real_lists_without_inline_forms(): void
    {
        $admin = User::factory()->admin()->create();
        $this->suggestion(User::factory()->cliente()->create());
        $lists = collect([Lista::factory()->create(), Lista::factory()->externa()->create(), Lista::factory()->anatel()->create()]);
        $inactive = Lista::factory()->inactive()->create();
        $response = $this->actingAs($admin)->get(route('sugestoes.index'));
        $response->assertOk()->assertSee('Ações da sugestão pending.example')
            ->assertSee('data-suggestion-action="aprovar"', false)->assertSee('data-suggestion-action="rejeitar"', false)
            ->assertSee('Aprovar sugestão')->assertSee('Rejeitar sugestão')->assertSee('Lista de destino')
            ->assertDontSee($inactive->nome)->assertSee('role="dialog" aria-modal="true"', false);
        foreach ($lists as $list) {
            $response->assertSee('<option value="'.$list->id.'">'.$list->nome.'</option>', false);
        }
        preg_match('/<tbody>(.*?)<\/tbody>/s', $response->getContent(), $matches);
        $this->assertStringNotContainsString('<select', $matches[1]);
        $this->assertStringNotContainsString('<form', $matches[1]);
        $view = file_get_contents(resource_path('views/sugestoes/partials/review-dialogs.blade.php'));
        foreach (['var(--surface)', 'var(--text)', 'showModal()', "dialog.addEventListener('close'"] as $expected) {
            $this->assertStringContainsString($expected, $view);
        }
        $this->assertStringNotContainsString('confirm(', $view);
    }

    public function test_status_filters_and_decided_rows_have_no_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->cliente()->create();
        foreach (['pending', 'approved', 'rejected'] as $status) {
            $this->suggestion($client, $status);
        }
        foreach (['pending' => 'Pendente', 'approved' => 'Aprovada', 'rejected' => 'Rejeitada'] as $status => $label) {
            $response = $this->actingAs($admin)->get(route('sugestoes.index', ['status' => $status]));
            $response->assertOk()->assertSee($status.'.example')->assertSee($label);
            foreach (array_diff(['pending', 'approved', 'rejected'], [$status]) as $other) {
                $response->assertDontSee($other.'.example');
            }
            if ($status !== 'pending') {
                $response->assertDontSee('data-suggestion-action')->assertDontSee('Aprovar sugestão')->assertDontSee('Rejeitar sugestão');
            }
        }
    }

    public function test_admin_decisions_preserve_backend_and_flash(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->cliente()->create();
        $list = Lista::factory()->create();
        $approved = $this->suggestion($client);
        $this->actingAs($admin)->from(route('sugestoes.index'))->post(route('sugestoes.aprovar', $approved), ['lista_id' => $list->id])
            ->assertRedirect(route('sugestoes.index'))->assertSessionHas('status');
        $this->assertDatabaseHas('sugestoes_dominios', ['id' => $approved->id, 'status' => 'approved', 'lista_id' => $list->id, 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('dominios', ['lista_id' => $list->id, 'dominio' => $approved->dominio, 'ativo' => true]);
        $rejected = $this->suggestion($client);
        $this->post(route('sugestoes.rejeitar', $rejected))->assertRedirect()->assertSessionHas('status', 'Sugestão rejeitada.');
        $this->assertDatabaseHas('sugestoes_dominios', ['id' => $rejected->id, 'status' => 'rejected', 'reviewed_by' => $admin->id]);
        $this->get(route('sugestoes.index'))->assertDontSee('data-suggestion-action');
    }

    public function test_client_scope_and_direct_decisions_remain_forbidden(): void
    {
        $client = User::factory()->cliente()->create();
        $own = $this->suggestion($client);
        $other = $this->suggestion(User::factory()->cliente()->create(), 'approved');
        $list = Lista::factory()->create();
        $this->actingAs($client)->get(route('sugestoes.index'))->assertOk()
            ->assertSee($own->dominio)->assertDontSee($other->dominio)->assertSee('+ Nova sugestão')
            ->assertDontSee('data-suggestion-action')->assertDontSee('suggestion-dialog')->assertDontSee('name="lista_id"', false);
        foreach ([$own, $other] as $suggestion) {
            $this->post(route('sugestoes.aprovar', $suggestion), ['lista_id' => $list->id])->assertForbidden();
            $this->post(route('sugestoes.rejeitar', $suggestion))->assertForbidden();
        }
        $this->assertSame('pending', $own->fresh()->status);
    }

    public function test_relationship_queries_do_not_grow_with_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $add = function (): void {
            $this->suggestion(User::factory()->cliente()->create())->update(['lista_id' => Lista::factory()->create()->id]);
        };
        $count = function () use ($admin): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->actingAs($admin)->get(route('sugestoes.index'))->assertOk()
                ->assertViewHas('sugestoes', fn ($rows) => $rows->every(fn ($row) => $row->relationLoaded('empresa') && $row->relationLoaded('criadoPor') && $row->relationLoaded('lista')));
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        $add();
        $single = $count();
        foreach (range(1, 19) as $index) {
            $add();
        }
        $this->assertSame($single, $count());
    }
}
