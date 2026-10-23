<?php

namespace Tests\Feature;

use App\Models\AnatelImport;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnatelUploadSafetyTest extends TestCase
{
    use RefreshDatabase;

    public static function uploadRoutes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('uploadRoutes')]
    public function test_losing_a_duplicate_insert_race_never_deletes_the_winning_document(bool $dashboard): void
    {
        Storage::fake('anatel');
        Queue::fake();
        $list = Lista::factory()->anatel()->create();
        $admin = User::factory()->admin()->create();
        AnatelImport::creating(static function (AnatelImport $import): void {
            // A competing request inserted this hash after the preflight check.
            DB::table('anatel_imports')->insert($import->getAttributes());
        });
        $file = UploadedFile::fake()->createWithContent('oficio.pdf', "%PDF-1.4\nrace.example\n%%EOF")->mimeType('application/pdf');
        $url = $dashboard ? route('anatel.dashboard.store') : route('anatel.imports.store', $list);
        $this->actingAs($admin)->post($url, ['lista_id' => $list->id, 'pdfs' => [$file]])->assertSessionHasErrors('pdfs');
        $winner = AnatelImport::sole();
        Storage::disk('anatel')->assertExists($winner->storage_path);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_files_in_one_selection_are_rejected_before_any_import_is_created(): void
    {
        Storage::fake('anatel');
        Queue::fake();
        $list = Lista::factory()->anatel()->create();
        $files = [];
        foreach (['one.pdf', 'two.pdf'] as $name) {
            $files[] = UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nsame.example\n%%EOF")->mimeType('application/pdf');
        }
        $this->actingAs(User::factory()->admin()->create())->post(route('anatel.dashboard.store'), ['lista_id' => $list->id, 'pdfs' => $files])->assertSessionHasErrors('pdfs');
        $this->assertDatabaseCount('anatel_imports', 0);
        $this->assertSame([], Storage::disk('anatel')->allFiles());
        Queue::assertNothingPushed();
    }
}
