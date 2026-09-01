<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServersPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_server_form_uses_simple_labels_static_dns_and_real_list_origins(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->for($empresa)->create(['tipo_dns' => 'unbound']);

        Lista::factory()->anatel()->create(['nome' => 'ANATEL']);
        Lista::factory()->externa('anatel')->create(['nome' => 'Anatel']);
        Lista::factory()->externa('phishing-army')->create(['nome' => 'Phishing Army (auto)']);
        Lista::factory()->externa('threatfox')->create(['nome' => 'ThreatFox — C2 / Botnet (auto)']);
        Lista::factory()->create(['empresa_id' => $empresa->id, 'nome' => 'malware']);

        $response = $this->actingAs($cliente)->get(route('servidores.edit', $servidor));

        $response->assertOk()
            ->assertSee('Editar servidor')
            ->assertSee('Configuração do servidor')
            ->assertSee('Acesso RPZ')
            ->assertSee('Listas habilitadas')
            ->assertSee('Pesquisar listas...')
            ->assertSee('endpoint-static-field')
            ->assertSee('name="tipo_dns" value="unbound"', false)
            ->assertSeeInOrder(['ANATEL', 'Catálogo', 'Anatel', 'Externa'])
            ->assertSeeInOrder(['Phishing Army (auto)', 'Externa'])
            ->assertSeeInOrder(['ThreatFox — C2 / Botnet (auto)', 'Externa'])
            ->assertSeeInOrder(['malware', 'Própria'])
            ->assertDontSee('Editar endpoint RPZ')
            ->assertDontSee('Configuração RPZ')
            ->assertDontSee('Fontes habilitadas')
            ->assertDontSee('Pesquisar fontes...')
            ->assertDontSee('BIND9 é suporte futuro')
            ->assertDontSee('<select class="form-control" id="tipo_dns"', false);
    }

    public function test_client_create_form_uses_server_language_and_defaults_dns_to_unbound(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $this->activeLicense($empresa, 1);
        Lista::factory()->create(['empresa_id' => $empresa->id]);

        $this->actingAs($cliente)->get(route('servidores.create'))
            ->assertOk()
            ->assertSee('Novo servidor')
            ->assertSee('Configuração do servidor')
            ->assertSee('Listas habilitadas')
            ->assertSee('Pesquisar listas...')
            ->assertSee('name="tipo_dns" value="unbound"', false)
            ->assertDontSee('Novo endpoint RPZ');
    }

    public function test_client_can_access_own_server_but_cannot_access_or_change_another_company_server(): void
    {
        $empresa = Empresa::factory()->create();
        $outraEmpresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidorProprio = Servidor::factory()->for($empresa)->create();
        $servidorAlheio = Servidor::factory()->for($outraEmpresa)->create();

        $this->actingAs($cliente)->get(route('servidores.show', $servidorProprio))->assertOk();
        $this->actingAs($cliente)->get(route('servidores.edit', $servidorProprio))->assertOk();
        $this->actingAs($cliente)->get(route('servidores.show', $servidorAlheio))->assertForbidden();
        $this->actingAs($cliente)->get(route('servidores.edit', $servidorAlheio))->assertForbidden();
        $this->actingAs($cliente)->put(route('servidores.update', $servidorAlheio), $this->serverPayload())->assertForbidden();
        $this->actingAs($cliente)->delete(route('servidores.destroy', $servidorAlheio))->assertForbidden();

        $this->assertDatabaseHas('servidores', ['id' => $servidorAlheio->id]);
    }

    public function test_client_cannot_link_another_company_list(): void
    {
        $empresa = Empresa::factory()->create();
        $outraEmpresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->for($empresa)->create();
        $listaAlheia = Lista::factory()->create(['empresa_id' => $outraEmpresa->id]);

        $this->actingAs($cliente)->put(route('servidores.update', $servidor), $this->serverPayload([
            'lista_ids' => [$listaAlheia->id],
        ]))->assertSessionHasErrors('lista_ids.0');

        $this->assertFalse($servidor->listas()->whereKey($listaAlheia->id)->exists());
    }

    public function test_client_cannot_create_server_above_license_limit(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $this->activeLicense($empresa, 1);
        Servidor::factory()->for($empresa)->create();

        $this->actingAs($cliente)->post(route('servidores.store'), $this->serverPayload([
            'nome' => 'Servidor acima do limite',
        ]))->assertSessionHasErrors('empresa_id');

        $this->assertSame(1, $empresa->servidores()->count());
    }

    public function test_server_log_does_not_show_list_history_from_before_the_server_was_linked(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->for($empresa)->create(['created_at' => now()->subHour()]);
        $lista = Lista::factory()->create(['nome' => 'Lista histórica']);
        Dominio::factory()->for($lista)->create([
            'dominio' => 'antes.example',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $servidor->listas()->attach($lista, [
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);
        Dominio::factory()->for($lista)->create([
            'dominio' => 'depois.example',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($cliente)->get(route('servidores.show', $servidor))
            ->assertOk()
            ->assertSee('Lista &quot;Lista histórica&quot; ganhou 1 domínio(s)', false)
            ->assertDontSee('ganhou 2 domínio(s)');
    }

    private function activeLicense(Empresa $empresa, int $maximum): void
    {
        $empresa->licencas()->delete();

        Licenca::create([
            'empresa_id' => $empresa->id,
            'starts_at' => today()->subDay(),
            'expires_at' => today()->addMonth(),
            'max_servidores' => $maximum,
            'status' => 'active',
        ]);
    }

    private function serverPayload(array $overrides = []): array
    {
        return array_merge([
            'nome' => 'Servidor cliente',
            'status' => 'active',
            'tipo_dns' => 'unbound',
            'bloqueio_modo' => 'nxdomain',
        ], $overrides);
    }
}
