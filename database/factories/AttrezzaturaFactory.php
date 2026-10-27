<?php

namespace Database\Factories;

use App\Enums\StatoAttrezzatura;
use App\Enums\Tipologia;
use App\Models\Attrezzatura;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attrezzatura> */
class AttrezzaturaFactory extends Factory
{
    protected $model = Attrezzatura::class;

    public function definition(): array
    {
        return [
            'codice' => (string) fake()->unique()->numberBetween(61970000, 61989999),
            'tipologia' => Tipologia::Sci,
            'marca' => fake()->randomElement(['ROSSIGNOL', 'ATOMIC', 'HEAD', 'SALOMON']),
            'modello' => 'EXPERIENCE 82',
            'misura' => (string) fake()->numberBetween(120, 180),
            'stato' => StatoAttrezzatura::Attiva,
        ];
    }
}
