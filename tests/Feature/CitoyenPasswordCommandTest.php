<?php

declare(strict_types=1);

use App\Models\Citoyen;

test('citoyen:password fails when the email is unknown', function () {
    Citoyen::create([
        'uid' => 'jdoe',
        'dn' => 'uid=jdoe,ou=Users,ou=Citoyens,dc=marche,dc=be',
        'mail' => 'jdoe@marche.be',
        'gosaMailQuota' => 250,
        'homeDirectory' => '/var/spool/dovecot/mail/j/jdoe',
        'gosaMailForwardingAddress' => 'jdoe@marche.be',
        'legacy_password' => '{SSHA}originallegacyvalue==',
    ]);

    $this->artisan('citoyen:password')
        ->expectsQuestion('Pour quelle adresse email', 'ghost@marche.be')
        ->expectsOutputToContain('not found')
        ->assertFailed();
});
