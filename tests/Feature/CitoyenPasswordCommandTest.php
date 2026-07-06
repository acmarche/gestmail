<?php

declare(strict_types=1);

use App\Models\Citoyen;
use App\Support\DovecotPassword;

function makeCitoyenForCommand(array $attributes = []): Citoyen
{
    return Citoyen::create([
        'uid' => 'jdoe',
        'dn' => 'uid=jdoe,ou=Users,ou=Citoyens,dc=marche,dc=be',
        'mail' => 'jdoe@marche.be',
        'gosaMailQuota' => 250,
        'homeDirectory' => '/var/spool/dovecot/mail/j/jdoe',
        'gosaMailForwardingAddress' => 'jdoe@marche.be',
        'legacy_password' => '{SSHA}originallegacyvalue==',
        ...$attributes,
    ]);
}

test('citoyen:password stores a SHA512-CRYPT hash for the matching citizen', function () {
    $citoyen = makeCitoyenForCommand();

    $this->artisan('citoyen:password')
        ->expectsQuestion('Pour quelle adresse email', 'jdoe@marche.be')
        ->expectsQuestion('Nouveau mot de passe pour jdoe', 'BrandNewPass123')
        ->expectsOutputToContain('Password changed')
        ->assertSuccessful();

    $citoyen->refresh();

    expect(DovecotPassword::check('BrandNewPass123', $citoyen->userPassword))->toBeTrue()
        ->and($citoyen->password_changed_at)->not->toBeNull();
});

test('citoyen:password fails when the email is unknown', function () {
    $this->artisan('citoyen:password')
        ->expectsQuestion('Pour quelle adresse email', 'ghost@marche.be')
        ->expectsOutputToContain('not found')
        ->assertFailed();
});
