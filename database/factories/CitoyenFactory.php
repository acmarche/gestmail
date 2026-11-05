<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VirtualUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Citoyen>
 */
final class CitoyenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => fn (): string => fake()->unique()->userName(),
            'mail' => fn (array $attributes): string => VirtualUser::factory()
                ->create(['username' => $attributes['uid'].'@marche.be'])
                ->username,
            'givenName' => fake()->firstName(),
            'sn' => fake()->lastName(),
            'homeDirectory' => fn (array $attributes): string => '/home/vmail/marche.be/'
                .mb_substr($attributes['mail'], 0, 1).'/'.$attributes['mail'],
            'auth_token' => Str::random(64),
            'charter_accepted_at' => null,
            'recovery_email' => null,
            'recovery_phone' => null,
        ];
    }
}
