<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('change user password command updates the password', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    $this->artisan('user:change-password')
        ->expectsSearch('Quel utilisateur ?', search: 'John', answers: [
            $user->id => 'John Doe',
        ], answer: $user->id)
        ->expectsQuestion("Nouveau mot de passe pour {$user->name}", 'NewPass123')
        ->expectsOutputToContain("Mot de passe modifié pour {$user->name}")
        ->assertSuccessful();

    $user->refresh();

    expect(Hash::check('NewPass123', $user->password))->toBeTrue();
});
