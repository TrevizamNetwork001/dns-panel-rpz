<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpzDiagnosticsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unbound_server_and_company_offer_local_read_only_diagnostics(): void
    {
        $company = Empresa::factory()->create();
        $server = Servidor::factory()->create(['empresa_id' => $company->id, 'tipo_dns' => 'unbound']);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([route('servidores.show', $server), route('empresas.show', $company)] as $url) {
            $this->get($url)->assertOk()
                ->assertSeeText('Conferir o RPZ no Unbound')
                ->assertSeeText('unbound-control stats_noreset')
                ->assertSeeText('num.rpz.action.*')
                ->assertSeeText('extended-statistics: yes')
                ->assertSeeText('rpz: applied')
                ->assertSeeText('Copiar diagnóstico');
        }
    }

    public function test_mikrotik_does_not_receive_unbound_diagnostic_commands(): void
    {
        $server = Servidor::factory()->create(['tipo_dns' => 'mikrotik']);
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('servidores.show', $server))->assertOk()
            ->assertDontSeeText('unbound-control stats_noreset');
    }
}
