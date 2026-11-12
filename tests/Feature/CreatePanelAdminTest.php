<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreatePanelAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_admin_is_created_with_a_hashed_password(): void
    {
        $this->artisan('panel:admin-create', ['email' => 'owner@example.test', '--name' => 'Operador'])
            ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'Senha-forte-12345')
            ->expectsQuestion('Confirme a senha', 'Senha-forte-12345')
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('Operador', $admin->name);
        $this->assertTrue(Hash::check('Senha-forte-12345', $admin->password));
        $this->assertNotSame('Senha-forte-12345', $admin->password);
    }

    public function test_repeat_install_does_not_change_existing_access(): void
    {
        $admin = User::factory()->admin()->create();
        $original = $admin->refresh()->getAttributes();

        $this->artisan('panel:admin-create', ['email' => 'other@example.test', '--if-missing' => true])
            ->expectsOutput('Administrador já cadastrado; acesso existente preservado.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($original, $admin->fresh()->getAttributes());
        $this->artisan('panel:admin-create', ['email' => 'other@example.test'])->assertFailed();
    }

    public function test_existing_client_email_cannot_be_promoted(): void
    {
        $client = User::factory()->create(['email' => 'client@example.test', 'role' => 'cliente']);
        $this->artisan('panel:admin-create', ['email' => $client->email])->assertFailed();
        $this->assertSame('cliente', $client->fresh()->role);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_mismatched_passwords_do_not_create_an_account(): void
    {
        $this->artisan('panel:admin-create', ['email' => 'owner@example.test'])
            ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'Senha-forte-12345')
            ->expectsQuestion('Confirme a senha', 'Outra-senha-12345')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_short_password_is_rejected(): void
    {
        $this->artisan('panel:admin-create', ['email' => 'owner@example.test'])
            ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'curta')
            ->expectsQuestion('Confirme a senha', 'curta')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
