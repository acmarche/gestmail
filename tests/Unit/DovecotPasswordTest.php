<?php

declare(strict_types=1);

use App\Support\DovecotPassword;

test('hash produces a scheme-prefixed SHA512-CRYPT value', function () {
    $hash = DovecotPassword::hash('SuperSecret123');

    expect($hash)->toStartWith('{SHA512-CRYPT}$6$');
});

test('check verifies a password against its own hash', function () {
    $hash = DovecotPassword::hash('SuperSecret123');

    expect(DovecotPassword::check('SuperSecret123', $hash))->toBeTrue()
        ->and(DovecotPassword::check('wrong-password', $hash))->toBeFalse();
});

test('check accepts the crypt string without the scheme prefix', function () {
    $hash = DovecotPassword::hash('SuperSecret123');
    $withoutScheme = mb_substr($hash, mb_strlen('{SHA512-CRYPT}'));

    expect(DovecotPassword::check('SuperSecret123', $withoutScheme))->toBeTrue();
});

test('each hash uses a random salt', function () {
    expect(DovecotPassword::hash('SuperSecret123'))
        ->not->toBe(DovecotPassword::hash('SuperSecret123'));
});
