<?php

namespace Tests\Feature\Api;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDominioQueryTest extends TestCase
{
    use RefreshDatabase;

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('teste')->plainTextToken];
    }

    public function test_desde_filter_returns_only_recently_updated_domains(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $antigo = Dominio::factory()->for($lista)->create(['dominio' => 'antigo.example']);
        $antigo->forceFill(['updated_at' => now()->subDays(5)])->saveQuietly();

        $recente = Dominio::factory()->for($lista)->create(['dominio' => 'recente.example']);
        $recente->forceFill(['updated_at' => now()])->saveQuietly();

        $response = $this->getJson(
            "/api/v1/listas/{$lista->id}/dominios?desde=" . now()->subDay()->toIso8601String(),
            $this->headersFor($admin)
        );

        $response->assertOk();
        $dominios = collect($response->json('data'))->pluck('dominio');
        $this->assertTrue($dominios->contains('recente.example'));
        $this->assertFalse($dominios->contains('antigo.example'));
    }

    public function test_invalid_desde_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $this->getJson("/api/v1/listas/{$lista->id}/dominios?desde=nao-e-uma-data", $this->headersFor($admin))
            ->assertStatus(422);
    }

    public function test_ativo_filter_narrows_results(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'ativo.example', 'ativo' => true]);
        Dominio::factory()->for($lista)->inativo()->create(['dominio' => 'inativo.example']);

        $response = $this->getJson("/api/v1/listas/{$lista->id}/dominios?ativo=1", $this->headersFor($admin));

        $dominios = collect($response->json('data'))->pluck('dominio');
        $this->assertTrue($dominios->contains('ativo.example'));
        $this->assertFalse($dominios->contains('inativo.example'));
    }

    public function test_lista_show_exposes_last_domain_change_timestamp(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create();

        $response = $this->getJson("/api/v1/listas/{$lista->id}", $this->headersFor($admin));

        $response->assertOk();
        $this->assertNotNull($response->json('data.ultima_alteracao_dominios'));
    }

    public function test_cliente_can_poll_own_catalog_lista_domains(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $catalogo = Lista::factory()->create(['empresa_id' => null]);
        Dominio::factory()->for($catalogo)->create(['dominio' => 'consultavel.example']);

        $response = $this->getJson("/api/v1/listas/{$catalogo->id}/dominios", $this->headersFor($cliente));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
