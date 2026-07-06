<?php

declare(strict_types=1);

use App\Ldap\CitoyenHandler;
use App\Models\Citoyen;
use App\Support\DovecotPassword;

function makeCitoyen(array $attributes = []): Citoyen
{
    return Citoyen::create([
        'uid' => 'jdoe',
        'dn' => 'uid=jdoe,ou=Users,ou=Citoyens,dc=marche,dc=be',
        'mail' => 'jdoe@marche.be',
        'gosaMailQuota' => 250,
        'homeDirectory' => '/var/spool/dovecot/mail/j/jdoe',
        'gosaMailForwardingAddress' => 'jdoe@marche.be',
        'legacy_password' => '{SSHA}gvtbEs2Jlegacyhashvalue==',
        ...$attributes,
    ]);
}

test('changing the password stores a SHA512-CRYPT hash in userPassword', function () {
    $citoyen = makeCitoyen();

    app(CitoyenHandler::class)->changePassword($citoyen, 'BrandNewPass123');

    $citoyen->refresh();

    expect($citoyen->userPassword)->toStartWith('{SHA512-CRYPT}$6$')
        ->and(DovecotPassword::check('BrandNewPass123', $citoyen->userPassword))->toBeTrue()
        ->and($citoyen->password_changed_at)->not->toBeNull();
});

test('changing the password preserves the legacy_password fallback', function () {
    $citoyen = makeCitoyen(['legacy_password' => '{SSHA}originallegacyvalue==']);

    app(CitoyenHandler::class)->changePassword($citoyen, 'BrandNewPass123');

    expect($citoyen->refresh()->legacy_password)->toBe('{SSHA}originallegacyvalue==');
});
