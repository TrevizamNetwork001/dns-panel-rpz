<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateRpzOperations;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RpzOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_list_contents_require_an_operations_credential_and_respect_company_visibility(): void
    {
        $company = Empresa::factory()->create();
        $otherCompany = Empresa::factory()->create();
        $ownList = Lista::factory()->for($company)->create(['origem' => 'manual']);
        $foreignList = Lista::factory()->for($otherCompany)->create(['origem' => 'manual']);
        $base = "/api/v1/operacao/empresas/{$company->id}/listas";

        $this->getJson("{$base}/{$ownList->id}/conteudo")->assertUnauthorized();

        $token = 'rpzo_'.bin2hex(random_bytes(32));
        Setting::set(AuthenticateRpzOperations::TOKEN_HASH_KEY, hash('sha256', $token));

        $this->withToken($token)->getJson("{$base}/{$ownList->id}/conteudo")->assertOk();
        $this->withToken($token)->getJson("{$base}/{$foreignList->id}/conteudo")->assertNotFound();
    }

    public function test_company_inventory_and_domain_search_distinguish_lists_rules_and_servers(): void
    {
        $company = Empresa::factory()->create();
        $other = Empresa::factory()->create();
        $server = Servidor::factory()->for($company)->create();
        $otherServer = Servidor::factory()->for($other)->create();
        $own = Lista::factory()->for($company)->create();
        $shared = Lista::factory()->create(['empresa_id' => null]);
        $unrelated = Lista::factory()->for($other)->create();
        $shared->servidores()->attach([$server->id, $otherServer->id]);
        $shared->dominios()->create(['dominio' => 'example.test', 'ativo' => true]);
        $unrelated->dominios()->create(['dominio' => 'example.test', 'ativo' => false]);
        $token = $this->operationsToken();

        $response = $this->withToken($token)->getJson("/api/v1/operacao/empresas/{$company->id}/listas")
            ->assertOk()->assertJsonCount(2, 'data');
        $lists = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($lists[$own->id]['propria']);
        $this->assertSame([], $lists[$own->id]['servidores']);
        $this->assertSame([$server->id], array_column($lists[$shared->id]['servidores'], 'id'));
        $this->withToken($token)->getJson('/api/v1/operacao/dominios?dominio=example.test')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['regra_ativa' => true])->assertJsonFragment(['regra_ativa' => false]);
    }

    public function test_operator_can_confirm_create_update_and_delete_with_auditable_receipts(): void
    {
        $company = Empresa::factory()->create();
        $operator = User::factory()->admin()->create();
        $operations = $this->operationsToken();
        $approval = 'rpza_'.bin2hex(random_bytes(32));
        config(['services.rpz_telegram' => [
            'confirmation_hash' => hash('sha256', $approval),
            'chat_id' => '-100', 'thread_id' => '2', 'operators' => json_encode(['10' => $operator->id]),
        ]]);
        $path = "/api/v1/operacao/empresas/{$company->id}/solicitacoes-lista-manual";
        $identity = ['telegram_user_id' => '10', 'telegram_chat_id' => '-100', 'telegram_thread_id' => '2'];

        $listId = null;
        foreach (['cadastrar' => 'lista.created', 'alterar' => 'lista.updated', 'remover' => 'lista.destroyed'] as $op => $audit) {
            $changes = match ($op) {
                'cadastrar' => ['nome' => 'Teste Telegram'],
                'alterar' => ['lista_id' => $listId, 'status' => 'inactive'],
                'remover' => ['lista_id' => $listId],
            };
            $id = $this->withToken($operations)->postJson($path, $changes + ['operacao' => $op, 'motivo' => 'Teste'])
                ->assertCreated()->json('data.solicitacao');
            $endpoint = "/api/v1/operacao/solicitacoes-excecao/{$id}/confirmacao";
            $this->withToken($operations)->postJson($endpoint, $identity)->assertUnauthorized();
            $listId = $this->withToken($approval)->postJson($endpoint, $identity)->assertOk()->json('data.lista_id');
            $this->withToken($approval)->postJson($endpoint, $identity)->assertOk()->assertJsonPath('data.lista_id', $listId);
            $this->assertDatabaseHas('audit_logs', ['action' => $audit, 'target_id' => $listId, 'user_id' => $operator->id]);
            if ($op === 'alterar') {
                $this->assertDatabaseHas('listas', ['id' => $listId, 'status' => 'inactive']);
            }
        }
        $this->assertDatabaseMissing('listas', ['id' => $listId]);
    }

    public function test_manual_list_confirmation_uses_the_rpz_deadline(): void
    {
        $company = Empresa::factory()->create();
        $operator = User::factory()->admin()->create();
        $operations = $this->operationsToken();
        $approval = 'rpza_'.bin2hex(random_bytes(32));
        config(['services.rpz_telegram' => [
            'confirmation_hash' => hash('sha256', $approval),
            'chat_id' => '-100', 'thread_id' => '2', 'operators' => json_encode(['10' => $operator->id]),
        ]]);
        $identity = ['telegram_user_id' => '10', 'telegram_chat_id' => '-100', 'telegram_thread_id' => '2'];
        $this->travelTo(Carbon::parse('2026-11-24T23:50:00Z'));

        $path = "/api/v1/operacao/empresas/{$company->id}/solicitacoes-lista-manual";
        $prepare = fn (string $name) => $this->withToken($operations)->postJson($path, [
            'operacao' => 'cadastrar', 'motivo' => 'Teste do prazo', 'nome' => $name,
        ])->assertCreated()->json('data');
        $valid = $prepare('Prévia válida');
        $expired = $prepare('Prévia expirada');
        $late = $prepare('Prévia após o prazo');
        $deadline = Carbon::parse($valid['expira_em']);
        $this->assertSame('2026-11-24 21:00:00 -03:00', $deadline->copy()
            ->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s P'));
        $this->assertSame($valid['expira_em'], $expired['expira_em']);

        $confirm = fn (array $preview) => $this->withToken($approval)->postJson(
            "/api/v1/operacao/solicitacoes-excecao/{$preview['solicitacao']}/confirmacao", $identity,
        );

        $this->travelTo($deadline->copy()->subSecond());
        $listId = $confirm($valid)->assertOk()->json('data.lista_id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'lista.created', 'target_id' => $listId]);

        $this->travelTo($deadline);
        $confirm($expired)->assertConflict();

        $this->travelTo($deadline->copy()->addSecond());
        $confirm($late)->assertConflict();
        $this->assertDatabaseMissing('listas', ['nome' => 'Prévia expirada']);
        $this->assertDatabaseMissing('listas', ['nome' => 'Prévia após o prazo']);
        $this->assertDatabaseHas('rpz_manual_list_requests', ['id' => $expired['solicitacao'], 'confirmed_at' => null]);
        $this->assertDatabaseHas('rpz_manual_list_requests', ['id' => $late['solicitacao'], 'confirmed_at' => null]);
    }

    private function operationsToken(): string
    {
        $token = 'rpzo_'.bin2hex(random_bytes(32));
        Setting::set(AuthenticateRpzOperations::TOKEN_HASH_KEY, hash('sha256', $token));

        return $token;
    }
}
