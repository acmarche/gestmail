<?php

declare(strict_types=1);

use App\Models\Citoyen;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

afterEach(function (): void {
    DirectoryFake::tearDown();
});

/**
 * @return array{dn: array<string>, uid: array<string>, mail: array<string>, homeDirectory: array<string>}
 */
function deletableLdapEntry(string $uid): array
{
    return [
        'dn' => ["uid={$uid},ou=Users,ou=Citoyens,dc=marche,dc=be"],
        'uid' => [$uid],
        'mail' => [$uid.'@marche.be'],
        'homeDirectory' => ['/var/spool/dovecot/mail/'.mb_substr($uid, 0, 1).'/'.$uid],
    ];
}

/**
 * Crée l'entrée SQL correspondant à un citoyen de l'annuaire.
 */
function makeSqlCitoyen(string $uid): Citoyen
{
    return Citoyen::create([
        'uid' => $uid,
        'dn' => "uid={$uid},ou=Users,ou=Citoyens,dc=marche,dc=be",
        'mail' => $uid.'@marche.be',
        'gosaMailQuota' => 250,
        'homeDirectory' => '/var/spool/dovecot/mail/'.mb_substr($uid, 0, 1).'/'.$uid,
        'gosaMailForwardingAddress' => $uid.'@marche.be',
    ]);
}

it('deletes the LDAP entry and the SQL citizen', function (): void {
    makeSqlCitoyen('jdoe');
    $kept = makeSqlCitoyen('asmith');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([deletableLdapEntry('jdoe')]),
            LdapFake::operation('delete')->once()->andReturn(true),
        ]);

    $this->artisan('citoyen:delete')
        ->expectsQuestion('Nom d\'utilisateur', 'jdoe')
        ->expectsConfirmation('Êtes-vous sûr de vouloir supprimer le compte de jdoe ?', 'yes')
        ->expectsOutputToContain('Le compte jdoe a été supprimé de l\'annuaire LDAP.')
        ->expectsOutputToContain('L\'entrée SQL de jdoe a été supprimée.')
        ->assertSuccessful();

    expect(Citoyen::query()->where('uid', 'jdoe')->exists())->toBeFalse()
        ->and(Citoyen::query()->whereKey($kept->getKey())->exists())->toBeTrue();
});

it('reports when the deleted LDAP account has no SQL counterpart', function (): void {
    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([deletableLdapEntry('jdoe')]),
            LdapFake::operation('delete')->once()->andReturn(true),
        ]);

    $this->artisan('citoyen:delete')
        ->expectsQuestion('Nom d\'utilisateur', 'jdoe')
        ->expectsConfirmation('Êtes-vous sûr de vouloir supprimer le compte de jdoe ?', 'yes')
        ->expectsOutputToContain('Aucune entrée SQL trouvée pour jdoe.')
        ->assertSuccessful();
});

it('keeps the SQL citizen when the deletion is not confirmed', function (): void {
    makeSqlCitoyen('jdoe');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect(LdapFake::operation('search')->andReturn([deletableLdapEntry('jdoe')]));

    $this->artisan('citoyen:delete')
        ->expectsQuestion('Nom d\'utilisateur', 'jdoe')
        ->expectsConfirmation('Êtes-vous sûr de vouloir supprimer le compte de jdoe ?', 'no')
        ->expectsOutputToContain('Suppression annulée.')
        ->assertSuccessful();

    expect(Citoyen::query()->where('uid', 'jdoe')->exists())->toBeTrue();
});

it('fails when the account does not exist in the directory', function (): void {
    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect(LdapFake::operation('search')->andReturn([]));

    $this->artisan('citoyen:delete')
        ->expectsQuestion('Nom d\'utilisateur', 'inconnu')
        ->expectsOutputToContain('Citizen with uid inconnu not found')
        ->assertFailed();
});
