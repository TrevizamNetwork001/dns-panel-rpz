<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Licenca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->company(),
            'documento' => fake()->numerify('##.###.###/0001-##'),
            'email_contato' => fake()->unique()->companyEmail(),
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Empresa $empresa) {
            Licenca::factory()->for($empresa)->create();
        });
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function semLicencaAtiva(): static
    {
        return $this->afterCreating(function (Empresa $empresa) {
            $empresa->licencas()->delete();
        });
    }
}
