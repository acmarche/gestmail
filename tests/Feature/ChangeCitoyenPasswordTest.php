<?php

declare(strict_types=1);

use App\Ldap\CitoyenHandler;
use App\Ldap\CitoyenLdap;
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

test('changing the password stores an Argon2id hash in userPassword', function () {
    $citoyen = makeCitoyen();

    app(CitoyenHandler::class)->changePassword($citoyen, 'BrandNewPass123');

    $citoyen->refresh();

    expect($citoyen->userPassword)->toStartWith('{ARGON2ID}$argon2id$')
        ->and(DovecotPassword::check('BrandNewPass123', $citoyen->userPassword))->toBeTrue()
        ->and($citoyen->password_changed_at)->not->toBeNull();
});

test('changing the password clears the legacy_password so userPassword is authoritative', function () {
    $citoyen = makeCitoyen(['legacy_password' => '{SSHA}originallegacyvalue==']);

    app(CitoyenHandler::class)->changePassword($citoyen, 'BrandNewPass123');

    expect($citoyen->refresh()->legacy_password)->toBeNull();
});

test('a sync update does not overwrite a password already migrated to userPassword', function () {
    $citoyen = makeCitoyen([
        'userPassword' => DovecotPassword::hash('BrandNewPass123'),
        'legacy_password' => null,
    ]);

    $ldap = new CitoyenLdap([
        'uid' => ['jdoe'],
        'mail' => ['jdoe@marche.be'],
        'userPassword' => ['{SSHA}staleldaphashvalue=='],
    ]);

    $data = $citoyen->syncableDataFromLdap($ldap);

    expect($data)->not->toHaveKey('userPassword')
        ->and($data)->not->toHaveKey('legacy_password');
});

test('a sync update still seeds legacy_password when no SQL password exists yet', function () {
    $citoyen = makeCitoyen(['userPassword' => null]);

    $ldap = new CitoyenLdap([
        'uid' => ['jdoe'],
        'mail' => ['jdoe@marche.be'],
        'userPassword' => ['{SSHA}freshldaphashvalue=='],
    ]);

    $data = $citoyen->syncableDataFromLdap($ldap);

    expect($data['legacy_password'])->toBe('{SSHA}freshldaphashvalue==')
        ->and($data)->toHaveKey('userPassword');
});
