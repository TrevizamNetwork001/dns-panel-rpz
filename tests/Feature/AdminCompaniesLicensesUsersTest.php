<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCompaniesLicensesUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_expiration_uses_calendar_days_for_future_today_and_expired_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 26)->setTime(23, 45));
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $empresa->licencas()->delete();

        foreach ([
            ['expires_at' => '2026-09-05', 'expected' => '10 dias restantes'],
            ['expires_at' => '2026-08-27', 'expected' => '1 dia restante'],
            ['expires_at' => '2026-08-26', 'expected' => 'Expira hoje'],
            ['expires_at' => '2026-08-23', 'expected' => 'Expirada há 3 dias'],
        ] as $case) {
            Licenca::factory()->for($empresa)->create([
                'starts_at' => '2026-08-20',
                'expires_at' => $case['expires_at'],
            ]);
        }

        $response = $this->actingAs($admin)->get(route('licencas.index'));

        $response->assertOk()
            ->assertSee('10 dias restantes')
            ->assertSee('1 dia restante')
            ->assertSee('Expira hoje')
            ->assertSee('Expirada há 3 dias')
            ->assertDontSee('8530499155787');
    }

    public function test_license_usage_and_form_use_endpoint_naming_with_correct_pluralization(): void
    {
        $admin = User::factory()->admin()->create();
        $empresaSingular = Empresa::factory()->create();
        $empresaPlural = Empresa::factory()->create();
        Servidor::factory()->for($empresaSingular)->create();
        Servidor::factory()->for($empresaPlural)->count(2)->create();
        $empresaSingular->licencas()->first()->update(['max_servidores' => 1]);
        $empresaPlural->licencas()->first()->update(['max_servidores' => 2]);

        $this->actingAs($admin)->get(route('licencas.index'))
            ->assertOk()
            ->assertSee('1 de 1 endpoint utilizado')
            ->assertSee('2 de 2 endpoints utilizados');

        $this->actingAs($admin)->get(route('licencas.create'))
            ->assertOk()
            ->assertSee('Máximo de Endpoints RPZ')
            ->assertDontSee('Máximo de servidores');
    }

    public function test_company_list_formats_only_compatible_cnpj_documents(): void
    {
        $admin = User::factory()->admin()->create();
        Empresa::factory()->create(['documento' => '86999450000190']);
        Empresa::factory()->create(['documento' => 'DOC-12345']);

        $this->actingAs($admin)->get(route('empresas.index'))
            ->assertOk()
            ->assertSee('86.999.450/0001-90')
            ->assertSee('DOC-12345');
    }

    public function test_company_listing_has_a_bounded_query_count_for_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        Empresa::factory()->count(20)->create()->each(
            fn (Empresa $empresa) => Servidor::factory()->for($empresa)->create()
        );
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin)->get(route('empresas.index'))->assertOk();

        $this->assertLessThan(10, $queries, 'A coluna Endpoints não deve executar uma query por empresa.');
    }

    public function test_admin_can_promote_a_client_and_company_is_cleared(): void
    {
        $admin = User::factory()->admin()->create();
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($admin)->put(route('usuarios.update', $cliente), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'role' => 'admin',
            'empresa_id' => $cliente->empresa_id,
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('users', ['id' => $cliente->id, 'role' => 'admin', 'empresa_id' => null]);
    }

    public function test_client_cannot_promote_users_with_a_forged_request(): void
    {
        $cliente = User::factory()->cliente()->create();
        $alvo = User::factory()->cliente()->create();

        $this->actingAs($cliente)->put(route('usuarios.update', $alvo), [
            'name' => $alvo->name,
            'email' => $alvo->email,
            'role' => 'admin',
            'empresa_id' => null,
        ])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $alvo->id, 'role' => 'cliente']);
    }

    public function test_last_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();

        $this->actingAs($admin)->put(route('usuarios.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'cliente',
            'empresa_id' => $empresa->id,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'empresa_id' => null]);
    }
}
