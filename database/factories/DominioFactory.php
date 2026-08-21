<?php

namespace Database\Factories;

use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dominio>
 */
class DominioFactory extends Factory
{
    protected $model = Dominio::class;

    public function definition(): array
    {
        return [
            'lista_id' => Lista::factory(),
            'dominio' => fake()->unique()->domainName(),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
