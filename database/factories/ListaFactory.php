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
            'nome' => fake()->unique()->words(2, true).' list',
            'descricao' => fake()->sentence(),
            'status' => 'active',
            'origem' => 'manual',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function externa(string $fonte = 'urlhaus', ?string $url = null, string $formato = 'hostfile'): static
    {
        return $this->state(fn () => [
            'origem' => 'externa',
            'fonte_externa' => $fonte,
            'fonte_url' => $url ?? "https://{$fonte}.example/feed.txt",
            'fonte_formato' => $formato,
            'sync_ativo' => true,
        ]);
    }

    public function anatel(): static
    {
        return $this->state(fn () => ['nome' => 'ANATEL', 'origem' => 'anatel', 'fonte_externa' => null, 'fonte_url' => null, 'sync_ativo' => false]);
    }
}
