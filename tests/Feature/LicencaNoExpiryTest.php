<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\ServerAllowedIp;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicencaNoExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_creates_license_with_expiry(): void
    {
        $empresa = Empresa::factory()->create();
        $this->postLicense($empresa, ['validity_type' => 'with_expiry', 'expires_at' => today()->addYear()->toDateString()])
            ->assertRedirect(route('licencas.index'));
        $this->assertSame(today()->addYear()->toDateString(), $empresa->licencas()->latest('id')->first()->expires_at->toDateString());
    }

    public function test_creates_license_without_expiry_and_saves_null(): void
    {
        $empresa = Empresa::factory()->create();
        $this->postLicense($empresa, ['validity_type' => 'no_expiry', 'expires_at' => null])
            ->assertRedirect(route('licencas.index'));
        $this->assertDatabaseHas('licencas', ['empresa_id' => $empresa->id, 'expires_at' => null]);
    }

    public function test_no_expiry_mode_normalizes_a_submitted_date_to_null(): void
    {
        $empresa = Empresa::factory()->create();
        $this->postLicense($empresa, ['validity_type' => 'no_expiry', 'expires_at' => '2099-12-31']);
        $this->assertDatabaseHas('licencas', ['empresa_id' => $empresa->id, 'expires_at' => null]);
        $this->assertDatabaseMissing('licencas', ['empresa_id' => $empresa->id, 'expires_at' => '2099-12-31']);
    }

    public function test_edits_license_from_expiring_to_without_expiry(): void
    {
        $licenca = Licenca::factory()->create();
        $this->putLicense($licenca, ['validity_type' => 'no_expiry', 'expires_at' => null])->assertRedirect(route('licencas.index'));
        $this->assertNull($licenca->fresh()->expires_at);
    }

    public function test_edits_license_from_without_expiry_to_expiring(): void
    {
        $licenca = Licenca::factory()->withoutExpiry()->create();
        $date = today()->addMonths(6)->toDateString();
        $this->putLicense($licenca, ['validity_type' => 'with_expiry', 'expires_at' => $date])->assertRedirect(route('licencas.index'));
        $this->assertSame($date, $licenca->fresh()->expires_at->toDateString());
    }

    public function test_validity_rule_handles_no_expiry_inactive_expired_and_future_license(): void
    {
        $empresa = Empresa::factory()->create();
        $empresa->licencas()->delete();
        $valid = Licenca::factory()->for($empresa)->withoutExpiry()->create();
        $inactive = Licenca::factory()->for($empresa)->withoutExpiry()->create(['status' => 'inactive']);
        $expired = Licenca::factory()->for($empresa)->expired()->create();
        $future = Licenca::factory()->for($empresa)->create(['starts_at' => today(), 'expires_at' => today()->addMonth()]);

        $this->assertTrue($valid->isValid());
        $this->assertFalse($inactive->isValid());
        $this->assertFalse($expired->isValid());
        $this->assertTrue($future->isValid());
        $this->assertEqualsCanonicalizing([$valid->id, $future->id], $empresa->licencas()->valid()->pluck('id')->all());
    }

    public function test_short_rpz_endpoint_accepts_active_license_without_expiry(): void
    {
        [$empresa] = $this->rpzCompany('short-no-expiry', 'active');
        $this->get("/rpz/{$empresa->rpz_slug}.zone")->assertOk();
    }

    public function test_short_rpz_endpoint_denies_inactive_license_without_expiry(): void
    {
        [$empresa] = $this->rpzCompany('short-inactive', 'inactive');
        $this->get("/rpz/{$empresa->rpz_slug}.zone")->assertNotFound();
    }

    public function test_legacy_endpoint_accepts_active_license_without_expiry(): void
    {
        [, $servidor] = $this->rpzCompany('legacy-no-expiry', 'active');
        $this->get("/rpz/{$servidor->token}.zone")->assertOk();
    }

    public function test_listing_and_company_detail_show_sem_vencimento_and_filter_it(): void
    {
        $licenca = Licenca::factory()->withoutExpiry()->create();
        $this->actingAs($this->admin)->get(route('licencas.index'))->assertOk()->assertSee('Sem vencimento');
        $this->actingAs($this->admin)->get(route('licencas.index', ['validity' => 'no_expiry']))
            ->assertOk()->assertSee($licenca->empresa->nome);
        $this->actingAs($this->admin)->get(route('empresas.show', $licenca->empresa))->assertOk()->assertSee('Sem vencimento');
    }

    public function test_with_expiry_mode_requires_a_valid_expiration_date(): void
    {
        $empresa = Empresa::factory()->create();
        $this->postLicense($empresa, ['validity_type' => 'with_expiry', 'expires_at' => null])
            ->assertSessionHasErrors('expires_at');
    }

    private function postLicense(Empresa $empresa, array $overrides)
    {
        return $this->actingAs($this->admin)->post(route('licencas.store'), $this->payload($empresa, $overrides));
    }

    private function putLicense(Licenca $licenca, array $overrides)
    {
        return $this->actingAs($this->admin)->put(route('licencas.update', $licenca), $this->payload($licenca->empresa, $overrides));
    }

    private function payload(Empresa $empresa, array $overrides): array
    {
        return array_merge([
            'empresa_id' => $empresa->id,
            'starts_at' => today()->toDateString(),
            'max_servidores' => 5,
            'status' => 'active',
        ], $overrides);
    }

    private function rpzCompany(string $slug, string $licenseStatus): array
    {
        $empresa = Empresa::factory()->create(['rpz_slug' => $slug]);
        $empresa->licencas()->delete();
        Licenca::factory()->for($empresa)->withoutExpiry()->create(['status' => $licenseStatus]);
        $servidor = Servidor::factory()->for($empresa)->create();
        ServerAllowedIp::create(['servidor_id' => $servidor->id, 'ip_cidr' => '127.0.0.1', 'status' => 'active']);

        return [$empresa, $servidor];
    }
}
