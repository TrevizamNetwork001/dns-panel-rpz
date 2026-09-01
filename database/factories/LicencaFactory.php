<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Licenca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Licenca>
 */
class LicencaFactory extends Factory
{
    protected $model = Licenca::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'max_servidores' => 5,
            'status' => 'active',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subYears(2),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function withoutExpiry(): static
    {
        return $this->state(fn () => [
            'expires_at' => null,
            'status' => 'active',
        ]);
    }
}
