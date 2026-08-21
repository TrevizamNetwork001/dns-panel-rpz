<?php

namespace Database\Factories;

use App\Models\Lista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lista>
 */
class ListaFactory extends Factory
{
    protected $model = Lista::class;

    public function definition(): array
    {
        return [
            'empresa_id' => null,
            'nome' => fake()->unique()->words(2, true) . ' list',
            'descricao' => fake()->sentence(),
            'status' => 'active',
            'origem' => 'manual',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function externa(string $fonte = 'urlhaus'): static
    {
        return $this->state(fn () => ['origem' => 'externa', 'fonte_externa' => $fonte]);
    }
}
