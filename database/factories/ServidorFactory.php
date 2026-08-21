<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Servidor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servidor>
 */
class ServidorFactory extends Factory
{
    protected $model = Servidor::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nome' => fake()->unique()->domainWord() . '-srv',
            'status' => 'active',
            'tipo_dns' => 'unbound',
            'bloqueio_modo' => 'nxdomain',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function redirect(): static
    {
        return $this->state(fn () => ['bloqueio_modo' => 'redirect']);
    }
}
