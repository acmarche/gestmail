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

test('check verifies the legacy schemes found in virtual_users', function (string $stored) {
    expect(DovecotPassword::check('OldPassword', $stored))->toBeTrue()
        ->and(DovecotPassword::check('WrongPassword', $stored))->toBeFalse();
})->with([
    'SSHA with a 4 byte salt' => fn () => '{SSHA}'.base64_encode(sha1('OldPasswordsalt', true).'salt'),
    'SSHA with a 7 byte salt' => fn () => '{SSHA}'.base64_encode(sha1('OldPasswordlongsal', true).'longsal'),
    'SHA' => fn () => '{SHA}'.base64_encode(sha1('OldPassword', true)),
    'lowercase md5' => fn () => '{md5}'.base64_encode(md5('OldPassword', true)),
    'CRYPT' => fn () => '{CRYPT}'.crypt('OldPassword', 'ab'),
]);

test('check refuses an unknown scheme', function () {
    expect(DovecotPassword::check('OldPassword', '{PLAIN}OldPassword'))->toBeFalse();
});

test('check refuses a malformed legacy hash', function () {
    expect(DovecotPassword::check('OldPassword', '{SSHA}not base64!'))->toBeFalse()
        ->and(DovecotPassword::check('OldPassword', '{SHA}'))->toBeFalse();
});
