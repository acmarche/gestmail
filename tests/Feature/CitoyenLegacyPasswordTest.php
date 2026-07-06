<?php

declare(strict_types=1);

use App\Ldap\CitoyenLdap;
use App\Models\Citoyen;

test('legacy password is mapped from the ldap userPassword attribute', function () {
    $hash = CitoyenLdap::cryptPassword('secret-pass');

    $ldap = new CitoyenLdap([
        'uid' => ['jdoe'],
        'mail' => ['jdoe@citoyen.marche.be'],
        'userPassword' => [$hash],
    ]);

    $data = Citoyen::generateDataFromLdap($ldap);

    expect($data['legacy_password'])->toBe($hash)
        ->and($hash)->toStartWith('{SSHA}');
});

test('legacy password is persisted on the citoyens table for dovecot auth', function () {
    $hash = CitoyenLdap::cryptPassword('secret-pass');

    $citoyen = Citoyen::create([
        'uid' => 'jdoe',
        'dn' => 'uid=jdoe,ou=Users,ou=Citoyens,dc=marche,dc=be',
        'mail' => 'jdoe@citoyen.marche.be',
        'gosaMailQuota' => 250,
        'homeDirectory' => '/var/spool/dovecot/mail/j/jdoe',
        'gosaMailForwardingAddress' => 'jdoe@citoyen.marche.be',
        'legacy_password' => $hash,
    ]);

    expect($citoyen->fresh()->legacy_password)->toBe($hash);
});
