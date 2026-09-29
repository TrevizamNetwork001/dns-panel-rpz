<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Services\RpzZoneBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpzZoneSerialTest extends TestCase
{
    use RefreshDatabase;

    public function test_serial_is_stable_when_the_domain_set_does_not_change(): void
    {
        $lista = Lista::factory()->create(['status' => 'active']);
        Dominio::factory()->for($lista)->create(['dominio' => 'estavel.example', 'ativo' => true]);
        $builder = app(RpzZoneBuilder::class);

        $primeiro = $builder->computeSerial($builder->listQuery($lista));
        $segundo = $builder->computeSerial($builder->listQuery($lista));

        $this->assertSame($primeiro, $segundo);
    }

    public function test_serial_changes_when_a_domain_is_added(): void
    {
        $lista = Lista::factory()->create(['status' => 'active']);
        Dominio::factory()->for($lista)->create(['dominio' => 'ja-existia.example', 'ativo' => true]);
        $builder = app(RpzZoneBuilder::class);

        $antes = $builder->computeSerial($builder->listQuery($lista));
        Dominio::factory()->for($lista)->create(['dominio' => 'novo.example', 'ativo' => true]);
        $depois = $builder->computeSerial($builder->listQuery($lista));

        $this->assertNotSame($antes, $depois);
    }

    public function test_serial_changes_when_a_domain_is_deactivated(): void
    {
        $lista = Lista::factory()->create(['status' => 'active']);
        $dominio = Dominio::factory()->for($lista)->create(['dominio' => 'vai-sair.example', 'ativo' => true]);
        $builder = app(RpzZoneBuilder::class);

        $antes = $builder->computeSerial($builder->listQuery($lista));
        $dominio->update(['ativo' => false]);
        $depois = $builder->computeSerial($builder->listQuery($lista));

        $this->assertNotSame($antes, $depois);
    }

    public function test_serial_is_a_valid_non_negative_integer_string(): void
    {
        $lista = Lista::factory()->create(['status' => 'active']);
        Dominio::factory()->for($lista)->create(['dominio' => 'qualquer.example', 'ativo' => true]);
        $builder = app(RpzZoneBuilder::class);

        $serial = $builder->computeSerial($builder->listQuery($lista));

        $this->assertMatchesRegularExpression('/^\d+$/', $serial);
        $this->assertLessThanOrEqual(4294967295, (int) $serial);
    }
}
