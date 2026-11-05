<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\VirtualUser>
 */
final class VirtualUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName().'@marche.be',
            'userPassword' => null,
            'legacy_password' => '{SSHA}'.fake()->sha1(),
            'quota_bytes' => 250,
            'active' => true,
        ];
    }
}
