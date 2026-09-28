<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnboundSegurancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_client_can_see_the_page_but_guest_is_redirected(): void
    {
        $this->get(route('servidores.unbound-seguranca'))->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('servidores.unbound-seguranca'))->assertOk()
            ->assertSeeText('CVE-2026-81642')
            ->assertSeeText('1.26.1 ou mais recente');

        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->get(route('servidores.unbound-seguranca'))->assertOk()
            ->assertSeeText('CVE-2026-81642');
    }
}
