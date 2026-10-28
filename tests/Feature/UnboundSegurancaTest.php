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
            ->assertSeeText('1.26.2 ou mais recente')
            ->assertSeeText('CVE-2026-97206')
            ->assertSeeText('CVE-2026-50106')
            ->assertSeeText('CVE-2026-96405')
            ->assertSeeText('CVE-2026-109125')
            ->assertSeeText('CVE-2026-35501')
            ->assertSeeText('backports')
            ->assertDontSeeText('parar de atualizar a lista de bloqueios sozinho');

        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->get(route('servidores.unbound-seguranca'))->assertOk()
            ->assertSeeText('CVE-2026-81642');
    }
}
