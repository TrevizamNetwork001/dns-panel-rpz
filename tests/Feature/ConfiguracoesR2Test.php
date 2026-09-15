<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracoesR2Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_backup_tab(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('configuracoes.index'))
            ->assertOk()
            ->assertSee('Backup externo (Cloudflare R2)');
    }

    public function test_cliente_cannot_reach_r2_routes(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)->put(route('configuracoes.r2.update'), ['ativo' => '1'])->assertStatus(403);
        $this->actingAs($cliente)->post(route('configuracoes.r2.test'))->assertStatus(403);
        $this->actingAs($cliente)->post(route('configuracoes.backup.run'))->assertStatus(403);
    }

    public function test_admin_can_save_r2_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('configuracoes.r2.update'), [
            'ativo' => '1',
            'account_id' => 'conta-123',
            'bucket' => 'dns-panel-rpz-backups',
            'access_key_id' => 'chave-acesso',
            'secret_access_key' => 'chave-secreta',
            'keep_days' => '45',
        ])->assertRedirect();

        $this->assertSame('45', Setting::get('r2_keep_days'));

        $this->assertSame('1', Setting::get('r2_ativo'));
        $this->assertSame('conta-123', Setting::get('r2_account_id'));
        $this->assertSame('dns-panel-rpz-backups', Setting::get('r2_bucket'));
        $this->assertSame('chave-acesso', Setting::getEncrypted('r2_access_key_id'));
        $this->assertSame('chave-secreta', Setting::getEncrypted('r2_secret_access_key'));
    }

    public function test_saving_without_new_keys_preserves_existing_ones(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::set('r2_access_key_id', 'chave-ja-salva');
        Setting::set('r2_secret_access_key', 'secreta-ja-salva');

        $this->actingAs($admin)->put(route('configuracoes.r2.update'), [
            'ativo' => '1',
            'account_id' => 'conta-123',
            'bucket' => 'meu-bucket',
        ]);

        $this->assertSame('chave-ja-salva', Setting::get('r2_access_key_id'));
        $this->assertSame('secreta-ja-salva', Setting::get('r2_secret_access_key'));
    }

    public function test_test_connection_fails_gracefully_when_not_configured(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('configuracoes.r2.test'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_manual_backup_button_runs_the_backup_command(): void
    {
        $admin = User::factory()->admin()->create();

        // Sem o script rpz-backup (só existe dentro da imagem Docker), o
        // comando falha graciosamente -- o que importa aqui é confirmar que
        // a rota dispara o artisan command e registra a auditoria, não o
        // resultado do script em si (coberto por deploy real).
        $this->actingAs($admin)->post(route('configuracoes.backup.run'))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.manual_triggered']);
    }
}
