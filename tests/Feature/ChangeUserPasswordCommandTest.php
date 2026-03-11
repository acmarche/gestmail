<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('change user password command updates the password', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $this->artisan('citoyen:change-password')
        ->expectsSearch('Quel utilisateur ?', search: 'John', answers: [
            $user->id => 'Doe John',
        ], answer: $user->id)
        ->expectsQuestion("Nouveau mot de passe pour {$user->fullName()}", 'NewPass123')
        ->expectsOutputToContain("Mot de passe modifié pour {$user->fullName()}")
        ->assertSuccessful();

    $user->refresh();

    expect(Hash::check('NewPass123', $user->password))->toBeTrue();
});
