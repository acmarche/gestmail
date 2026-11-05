<?php

declare(strict_types=1);

use App\Models\Citoyen;
use App\Service\CitoyenHandler;
use App\Support\DovecotPassword;

test('changing the password stores an Argon2id hash in virtual_users', function () {
    $citoyen = Citoyen::factory()->create();

    app(CitoyenHandler::class)->changePassword($citoyen, 'BrandNewPass123');

    $userPassword = $citoyen->virtualUser->refresh()->userPassword;

    expect($userPassword)->toStartWith('{ARGON2ID}$argon2id$')
        ->and(DovecotPassword::check('BrandNewPass123', $userPassword))->toBeTrue();
});

test('changing the password clears the legacy_password so userPassword is authoritative', function () {
    $citoyen = Citoyen::factory()->create();

    app(CitoyenHandler::class)->changePassword($citoyen, 'BrandNewPass123');

    expect($citoyen->virtualUser->refresh()->legacy_password)->toBeNull();
});

test('changing the password only touches the account of the citizen', function () {
    $citoyen = Citoyen::factory()->create();
    $other = Citoyen::factory()->create();

    app(CitoyenHandler::class)->changePassword($citoyen, 'BrandNewPass123');

    expect($other->virtualUser->refresh()->userPassword)->toBeNull()
        ->and($other->virtualUser->legacy_password)->not->toBeNull();
});
