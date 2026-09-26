<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaContactFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_clear_optional_contact_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('empresas.create'))->assertOk()
            ->assertSee('name="nome_fantasia"', false)->assertSee('type="tel"', false);
        $data = ['nome' => 'Empresa exemplo', 'status' => 'active', 'nome_fantasia' => 'Minha marca', 'telefone' => '+55 (11) 99999-9999'];
        $this->post(route('empresas.store'), $data)->assertRedirect(route('empresas.index'));
        $empresa = Empresa::where('nome', $data['nome'])->firstOrFail();
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id] + $data);
        $this->get(route('empresas.edit', $empresa))->assertOk()
            ->assertSee('value="Minha marca"', false)->assertSee('value="+55 (11) 99999-9999"', false);
        $data['nome_fantasia'] = 'Nova marca';
        $data['telefone'] = '(21) 3333-4444';
        $this->put(route('empresas.update', $empresa), $data)->assertRedirect(route('empresas.index'));
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id] + $data);
        $data['nome_fantasia'] = '';
        $data['telefone'] = '';
        $this->put(route('empresas.update', $empresa), $data)->assertRedirect(route('empresas.index'));
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'nome_fantasia' => null, 'telefone' => null]);
    }

    public function test_contact_fields_are_optional_and_have_length_limits(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $data = ['nome' => 'Sem contato', 'status' => 'active'];
        $this->post(route('empresas.store'), $data)->assertRedirect(route('empresas.index'));
        $this->post(route('empresas.store'), $data + ['nome_fantasia' => str_repeat('a', 256), 'telefone' => str_repeat('1', 33)])
            ->assertSessionHasErrors(['nome_fantasia', 'telefone']);
    }

    public function test_admin_company_uses_current_layout_and_links_to_prefilled_server_form(): void
    {
        $empresa = Empresa::factory()->create(['nome_fantasia' => 'Marca atual', 'telefone' => '(11) 99999-8888']);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('empresas.show', $empresa))->assertOk()
            ->assertSee('company-profile', false)
            ->assertSee('Marca atual')->assertSee('(11) 99999-8888')
            ->assertSee('company-rpz-access')->assertSee('Criar acesso');
        $this->get(route('servidores.create', ['empresa_id' => $empresa->id]))->assertOk()
            ->assertViewHas('servidor', fn ($servidor) => $servidor->empresa_id === $empresa->id);
        $this->get(route('servidores.create'))->assertOk()
            ->assertViewHas('servidor', fn ($servidor) => $servidor->empresa_id === null);
    }
}
