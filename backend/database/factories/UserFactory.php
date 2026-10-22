<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prenom' => fake()->firstName(),
            'nom' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'MotDePasse-Test-123',
            'remember_token' => Str::random(10),
            'role_id' => fn () => Role::firstOrCreate(['code' => Role::ETUDIANT], ['libelle' => 'Étudiant'])->id,
        ];
    }

    public function role(string $code): static
    {
        return $this->state(fn () => ['role_id' => Role::where('code', $code)->value('id')]);
    }

    /** Simule une double authentification déjà activée et confirmée. */
    public function avecMfa(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-1', 'code-2'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
