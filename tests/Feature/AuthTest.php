<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_succeeds(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('SenhaForte123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'SenhaForte123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_password_fails(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('SenhaForte123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_failed_login_is_recorded_in_audit_log(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('SenhaForte123!'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_brute_force_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('SenhaForte123!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'errada']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Muitas tentativas',
            collect(session('errors')->get('email'))->first()
        );
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }
}
