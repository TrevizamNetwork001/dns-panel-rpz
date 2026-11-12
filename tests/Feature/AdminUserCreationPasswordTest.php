<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserCreationPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_choose_a_password_and_client_can_log_in(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $password = 'Cliente!123';

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Cliente', 'email' => 'cliente@example.test',
            'role' => 'cliente', 'empresa_id' => $empresa->id,
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors();

        $user = User::where('email', 'cliente@example.test')->sole();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertStringNotContainsString($password, session('status'));
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => $password])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_blank_password_keeps_automatic_generation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Novo admin', 'email' => 'novo@example.test', 'role' => 'admin',
            'password' => '', 'password_confirmation' => '',
        ])->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors();

        $password = Str::after(session('status'), 'Senha temporária (mostrada só agora): ');
        $this->assertTrue(Hash::check($password, User::where('email', 'novo@example.test')->sole()->password));
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        foreach ([['fraca', 'fraca'], ['Cliente!123', 'Diferente!123']] as [$password, $confirmation]) {
            $this->actingAs($admin)->post(route('usuarios.store'), [
                'name' => 'Cliente', 'email' => 'rejeitado@example.test', 'role' => 'admin',
                'password' => $password, 'password_confirmation' => $confirmation,
            ])->assertSessionHasErrors('password');
            $this->assertDatabaseMissing('users', ['email' => 'rejeitado@example.test']);
            $this->assertArrayNotHasKey('password', session('_old_input', []));
        }
    }
}
