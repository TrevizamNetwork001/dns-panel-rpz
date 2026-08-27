<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientCompanyPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_company_uses_simple_labels_formatted_document_and_license_summary(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 27)->startOfDay());
        $empresa = Empresa::factory()->create([
            'nome' => 'speednet',
            'documento' => '86999450000190',
            'email_contato' => 'contato@teste.cn.nrt',
            'status' => 'active',
        ]);
        $empresa->licencas()->delete();
        Licenca::factory()->for($empresa)->create([
            'starts_at' => '2026-08-20',
            'expires_at' => '2026-09-05',
            'max_servidores' => 2,
            'status' => 'active',
        ]);
        $cliente = User::factory()->cliente($empresa)->create();
        Servidor::factory()->for($empresa)->create(['nome' => 'dns01']);
        Lista::factory()->create(['empresa_id' => $empresa->id, 'nome' => 'malware']);

        $response = $this->actingAs($cliente)->get(route('empresas.show', $empresa));

        $response->assertOk()
            ->assertSee('Documento')
            ->assertSee('86.999.450/0001-90')
            ->assertSee('E-mail de contato')
            ->assertSee('contato@teste.cn.nrt')
            ->assertSee('Ativa')
            ->assertSee('Servidores')
            ->assertSee('dns01')
            ->assertSee('Listas')
            ->assertSee('malware')
            ->assertSee('Licença')
            ->assertSee('20/08/2026')
            ->assertSee('05/09/2026')
            ->assertSee('9 dias restantes')
            ->assertSee('1 de 2 servidores utilizados')
            ->assertDontSee('Endpoints RPZ')
            ->assertDontSee('Fontes')
            ->assertDontSee('Editar');
    }

    public function test_client_company_preserves_non_cnpj_document_and_singular_server_usage(): void
    {
        $empresa = Empresa::factory()->create(['documento' => 'DOC-12345']);
        $empresa->licencas()->first()->update(['max_servidores' => 1]);
        $cliente = User::factory()->cliente($empresa)->create();
        Servidor::factory()->for($empresa)->create();

        $this->actingAs($cliente)->get(route('empresas.show', $empresa))
            ->assertOk()
            ->assertSee('DOC-12345')
            ->assertSee('1 de 1 servidor utilizado');
    }

    public function test_client_only_sees_its_company_resources_and_cannot_manage_another_company(): void
    {
        $empresa = Empresa::factory()->create();
        $outraEmpresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        Servidor::factory()->for($empresa)->create(['nome' => 'Servidor próprio']);
        Servidor::factory()->for($outraEmpresa)->create(['nome' => 'Servidor alheio']);
        Lista::factory()->create(['empresa_id' => $empresa->id, 'nome' => 'Lista própria']);
        Lista::factory()->create(['empresa_id' => $outraEmpresa->id, 'nome' => 'Lista alheia']);

        $this->actingAs($cliente)->get(route('empresas.show', $empresa))
            ->assertOk()
            ->assertSee('Servidor próprio')
            ->assertSee('Lista própria')
            ->assertDontSee('Servidor alheio')
            ->assertDontSee('Lista alheia');

        $this->actingAs($cliente)->get(route('empresas.show', $outraEmpresa))->assertForbidden();
        $this->actingAs($cliente)->get(route('empresas.edit', $outraEmpresa))->assertForbidden();
        $this->actingAs($cliente)->put(route('empresas.update', $outraEmpresa), [])->assertForbidden();
    }

    public function test_client_company_page_has_a_bounded_query_count(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        Servidor::factory()->for($empresa)->count(10)->create();
        Lista::factory()->create(['empresa_id' => $empresa->id]);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($cliente)->get(route('empresas.show', $empresa))->assertOk();

        $this->assertLessThan(10, $queries, 'Minha empresa não deve executar uma query por servidor ou lista.');
    }
}
