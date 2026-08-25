<?php

namespace Tests\Feature;

use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListaCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_manual_lista(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/listas', [
            'nome' => 'Lista manual de teste',
            'status' => 'active',
        ]);

        $lista = Lista::where('nome', 'Lista manual de teste')->first();
        $response->assertRedirect(route('listas.show', $lista));
        $this->assertSame('manual', $lista->origem);
        $this->assertNull($lista->fonte_url);
    }

    public function test_admin_can_create_external_lista_with_custom_url(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/listas', [
            'nome' => 'Feed customizado',
            'status' => 'active',
            'origem' => 'externa',
            'fonte_url' => 'https://exemplo.com/blocklist.txt',
            'fonte_formato' => 'plain',
        ]);

        $lista = Lista::where('nome', 'Feed customizado')->first();
        $response->assertRedirect(route('listas.show', $lista));
        $this->assertSame('externa', $lista->origem);
        $this->assertSame('https://exemplo.com/blocklist.txt', $lista->fonte_url);
        $this->assertSame('plain', $lista->fonte_formato);
        $this->assertTrue($lista->sync_ativo);
    }

    public function test_creating_external_lista_without_url_fails_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/listas', [
            'nome' => 'Feed sem URL',
            'status' => 'active',
            'origem' => 'externa',
        ]);

        $response->assertSessionHasErrors('fonte_url');
        $this->assertDatabaseMissing('listas', ['nome' => 'Feed sem URL']);
    }

    public function test_show_page_handles_large_lista_without_high_memory_usage(): void
    {
        // Regressao: ListaController::show fazia $lista->load([..., 'dominios']),
        // hidratando todos os dominios como models Eloquent na memoria. A view so
        // usa $lista->dominios()->count() e ->take(10)->get() (queries proprias,
        // ignoram o eager load), entao pra listas grandes (feeds externos com
        // dezenas de milhares de dominios) isso estourava o memory_limit e
        // devolvia 500. Reproduzido em producao na lista #10 (157k dominios).
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $agora = now();
        $rows = [];
        for ($i = 0; $i < 50000; $i++) {
            $rows[] = [
                'lista_id' => $lista->id,
                'dominio' => "dominio-teste-{$i}.example",
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('dominios')->insert($chunk);
        }

        $limiteAnterior = ini_set('memory_limit', '128M');
        try {
            $response = $this->actingAs($admin)->get(route('listas.show', $lista));
        } finally {
            ini_set('memory_limit', $limiteAnterior);
        }

        $response->assertStatus(200);
        $response->assertSee('Domínios (50000)');
    }
}
