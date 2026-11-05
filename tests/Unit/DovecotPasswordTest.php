<?php

declare(strict_types=1);

use App\Support\DovecotPassword;

test('hash produces a scheme-prefixed Argon2id value', function () {
    $hash = DovecotPassword::hash('SuperSecret123');

    expect($hash)->toStartWith('{ARGON2ID}$argon2id$')
        ->and($hash)->toContain('m=32768,t=4,p=1');
});

test('check verifies a password against its own hash', function () {
    $hash = DovecotPassword::hash('SuperSecret123');

    expect(DovecotPassword::check('SuperSecret123', $hash))->toBeTrue()
        ->and(DovecotPassword::check('wrong-password', $hash))->toBeFalse();
});

test('check accepts the hash without the scheme prefix', function () {
    $hash = DovecotPassword::hash('SuperSecret123');
    $withoutScheme = mb_substr($hash, mb_strlen('{ARGON2ID}'));

    expect(DovecotPassword::check('SuperSecret123', $withoutScheme))->toBeTrue();
});

test('each hash uses a random salt', function () {
    expect(DovecotPassword::hash('SuperSecret123'))
        ->not->toBe(DovecotPassword::hash('SuperSecret123'));
});
