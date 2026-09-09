<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminUserPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_uses_one_time_modal_without_leaking_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->cliente()->create();
        $before = $user->fresh()->getRawOriginal();
        Log::spy();

        $response = $this->actingAs($admin)->from(route('usuarios.index'))
            ->post(route('usuarios.reset-password', $user));
        $response->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('status', 'Senha redefinida com sucesso.')
            ->assertSessionHas('admin_user_password_reset');
        $password = session('admin_user_password_reset.password');
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Za-km-z2-9]{5}(?:-[A-HJ-NP-Za-km-z2-9]{5}){3}$/', $password);
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertNull(parse_url($response->headers->get('Location'), PHP_URL_QUERY));
        $response->assertDontSee($password);
        $this->assertStringNotContainsString($password, session('status'));
        $after = $user->fresh()->getRawOriginal();
        unset($before['password'], $before['updated_at'], $after['password'], $after['updated_at']);
        $this->assertSame($before, $after, 'A redefinição deve preservar os demais atributos e regras do usuário.');
        $audit = AuditLog::where('action', 'user.password_reset')->sole();
        $this->assertSame("Senha de {$user->email} redefinida pelo admin", $audit->description);
        $this->assertStringNotContainsString($password, AuditLog::all()->toJson());
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $method) {
            Log::shouldNotHaveReceived($method);
        }

        $page = $this->get(route('usuarios.index'));
        $page->assertOk()->assertSee('id="user-password-result"', false)
            ->assertSee('Senha temporária gerada')->assertSee($password)
            ->assertSessionMissing('admin_user_password_reset');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->assertSame(1, substr_count($page->getContent(), $password));
        preg_match('/<div class="alert-success">(.*?)<\/div>/s', $page->getContent(), $flash);
        $this->assertSame('Senha redefinida com sucesso.', $flash[1]);
        $this->get(route('usuarios.index'))->assertOk()->assertDontSee($password)
            ->assertDontSee('id="user-password-result"', false);
    }

    public function test_client_cannot_reset_a_password_or_receive_sensitive_result(): void
    {
        $client = User::factory()->cliente()->create();
        $target = User::factory()->admin()->create();
        $hash = $target->password;
        $this->actingAs($client)->post(route('usuarios.reset-password', $target))
            ->assertForbidden()->assertSessionMissing('admin_user_password_reset');
        $this->get(route('usuarios.index'))->assertForbidden();
        $this->assertSame($hash, $target->fresh()->password);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.password_reset']);
    }

    public function test_guest_cannot_reset_a_password(): void
    {
        $target = User::factory()->admin()->create();
        $hash = $target->password;
        $this->post(route('usuarios.reset-password', $target))->assertRedirect(route('login'));
        $this->assertSame($hash, $target->fresh()->password);
    }

    public function test_listing_connects_confirmation_and_preserves_user_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk()
            ->assertSee('id="user-password-confirm"', false)
            ->assertSee('role="dialog" aria-modal="true"', false)
            ->assertSee('Gerar senha temporária')->assertSee('Cancelar')
            ->assertSee('Editar')->assertSee('Redefinir senha')->assertSee('Remover')
            ->assertDontSee("confirm('Gerar nova senha", false)
            ->assertDontSee('id="user-password-result"', false);
    }

    public function test_reset_does_not_change_last_admin_protections(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('usuarios.reset-password', $admin))->assertRedirect(route('usuarios.index'));
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->delete(route('usuarios.destroy', $admin))->assertSessionHasErrors('usuario');
        $this->assertModelExists($admin);
    }
}
