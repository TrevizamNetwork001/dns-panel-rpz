<?php

namespace Tests\Feature;

use App\Jobs\ProcessAnatelImport;
use App\Models\{AnatelImport, Dominio, Lista, Servidor, User};
use App\Services\{AnatelImporter, AnatelPdfExtractor};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\TestCase;

class WorkflowConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_anatel_upload_uses_the_bound_list_and_waits_for_approval(): void
    {
        Storage::fake('anatel');
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $list = Lista::factory()->anatel()->create();
        $other = Lista::factory()->anatel()->create();
        $file = UploadedFile::fake()->createWithContent('oficio.pdf', "%PDF-1.4\nnovo.example\n%%EOF")->mimeType('application/pdf');
        $this->actingAs($admin)->post(route('anatel.imports.store', $list), [
            'lista_id' => $other->id, 'pdfs' => [$file],
        ])->assertRedirect();
        $import = AnatelImport::sole();
        $this->assertSame($list->id, $import->lista_id);
        $this->assertSame('pending', $import->status);
        Queue::assertPushed(ProcessAnatelImport::class, 1);
        $this->assertDatabaseCount('dominios', 0);
        $extractor = new class extends AnatelPdfExtractor {
            public function extract(array $paths): array
            {
                return ['status' => 'ok', 'files' => [['filename' => 'oficio.pdf', 'pages' => 1, 'candidates' => 1]], 'domains' => ['novo.example']];
            }
        };
        (new ProcessAnatelImport($import->id))->handle($extractor, app(AnatelImporter::class));
        $this->assertSame('awaiting_approval', $import->fresh()->status);
        $this->assertDatabaseCount('dominios', 0);
        $this->post(route('anatel.approve', $import))->assertRedirect();
        $this->assertDatabaseHas('dominios', ['lista_id' => $list->id, 'dominio' => 'novo.example']);
    }

    public function test_old_domain_pages_redirect_with_source_and_search_context(): void
    {
        $admin = User::factory()->admin()->create();
        $list = Lista::factory()->create();
        $this->actingAs($admin)->get(route('listas.dominios.index', ['lista' => $list, 'dominio' => 'teste', 'status' => 'ativos', 'page' => 2]))
            ->assertRedirect(route('dominios.index', ['dominio' => 'teste', 'status' => 'ativos', 'page' => 2, 'fonte_id' => $list->id]));
        $this->get(route('consulta.index', ['dominio' => 'teste.example']))
            ->assertRedirect(route('dominios.index', ['dominio' => 'teste.example']));
    }

    public function test_central_domain_page_keeps_bulk_import_only_for_editable_sources(): void
    {
        $admin = User::factory()->admin()->create();
        $manual = Lista::factory()->create();
        $external = Lista::factory()->externa()->create();
        $this->actingAs($admin)->get(route('dominios.index', ['fonte_id' => $manual->id]))
            ->assertOk()->assertSee('Importar em lote nesta fonte');
        $this->get(route('dominios.index', ['fonte_id' => $external->id]))
            ->assertOk()->assertDontSee('Importar em lote nesta fonte');
        $this->post(route('listas.dominios.bulk', $manual), ['dominios' => "um.example\ndois.example"])
            ->assertRedirect(route('dominios.index', ['fonte_id' => $manual->id]));
        $this->assertSame(2, $manual->dominios()->count());
        $this->post(route('listas.dominios.bulk', $external), ['dominios' => 'tres.example'])->assertSessionHasErrors();
        $this->assertSame(0, $external->dominios()->count());
    }

    public function test_admin_source_details_do_not_repeat_server_or_domain_listings(): void
    {
        $admin = User::factory()->admin()->create();
        $list = Lista::factory()->create();
        $server = Servidor::factory()->create(['nome' => 'Servidor que fica na tela propria']);
        $list->servidores()->attach($server);
        Dominio::factory()->for($list)->create(['dominio' => 'dominio-da-previa.example']);
        $response = $this->actingAs($admin)->get(route('listas.show', $list))->assertOk()
            ->assertSee('Resumo operacional')->assertSee('Gerenciar vínculos')
            ->assertDontSee('Servidor que fica na tela propria')->assertDontSee('dominio-da-previa.example');
        $this->assertFalse($response->viewData('lista')->relationLoaded('servidores'));
    }
}
