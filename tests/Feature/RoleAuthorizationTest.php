<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_cliente_cannot_access_empresas_list(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get('/empresas')->assertForbidden();
    }

    public function test_admin_can_access_empresas_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/empresas')->assertOk();
    }

    public function test_cliente_cannot_view_another_companys_servidor(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresaA)->create();
        $servidorDaOutraEmpresa = Servidor::factory()->for($empresaB)->create();

        $this->actingAs($cliente)
            ->get("/servidores/{$servidorDaOutraEmpresa->id}")
            ->assertForbidden();
    }

    public function test_cliente_can_view_own_servidor(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->for($empresa)->create();

        $this->actingAs($cliente)
            ->get("/servidores/{$servidor->id}")
            ->assertOk();
    }

    public function test_cliente_cannot_create_lista(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get('/listas/create')->assertForbidden();
    }

    public function test_cliente_cannot_manage_users(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get('/usuarios')->assertForbidden();
    }

    public function test_cliente_cannot_view_auditoria(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get('/auditoria')->assertForbidden();
    }

    public function test_cliente_cannot_view_seguranca(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get('/seguranca')->assertForbidden();
    }
}
