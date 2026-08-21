<?php

namespace Tests\Feature;

use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
