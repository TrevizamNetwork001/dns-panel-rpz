<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_emits_only_source_ip_to_fail2ban_log(): void
    {
        $logger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $logger->shouldReceive('warning')->once()->with('panel-login-failed ip=2001:db8::42');
        \Illuminate\Support\Facades\Log::shouldReceive('channel')->once()->with('security')->andReturn($logger);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::42'])
            ->post('/login', ['email' => 'test@example.test', 'password' => 'invalid-secret'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed', 'ip_address' => '2001:db8::42']);
    }

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
        $this->assertNotNull($user->fresh()->last_login_at);
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
