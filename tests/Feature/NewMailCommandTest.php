<?php

declare(strict_types=1);

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Support\Facades\File;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

beforeEach(function (): void {
    $this->home = sys_get_temp_dir().'/gestmail-test-'.uniqid();
    File::makeDirectory($this->home.'/Maildir/new', 0755, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->home);
    DirectoryFake::tearDown();
});

/**
 * @return array{dn: array<string>, uid: array<string>, mail: array<string>, homeDirectory: array<string>}
 */
function ldapEntry(string $uid, string $homeDirectory): array
{
    return [
        'dn' => ["uid={$uid},ou=Users,ou=Citoyens,dc=marche,dc=be"],
        'uid' => [$uid],
        'mail' => [$uid.'@marche.be'],
        'homeDirectory' => [$homeDirectory],
    ];
}

/**
 * @param  array<int, array<string, array<string>>>  $entries
 */
function fakeLdapReturning(array $entries): void
{
    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect(LdapFake::operation('search')->andReturn($entries));
}

it('counts the messages present in Maildir/new', function (): void {
    File::put($this->home.'/Maildir/new/1234567.mail', 'body');
    File::put($this->home.'/Maildir/new/1234568.mail', 'body');

    expect(app(LdapCitoyenRepository::class)->countNewMails($this->home))->toBe(2);
});

it('returns null when the Maildir/new directory does not exist', function (): void {
    $repository = app(LdapCitoyenRepository::class);

    expect($repository->countNewMails($this->home.'/nope'))->toBeNull()
        ->and($repository->countNewMails(null))->toBeNull();
});

it('lists citizens with their unread message count', function (): void {
    File::put($this->home.'/Maildir/new/1234567.mail', 'body');
    fakeLdapReturning([ldapEntry('jdoe', $this->home)]);

    $this->artisan('citoyen:new-mail')
        ->expectsOutputToContain('jdoe@marche.be')
        ->expectsOutputToContain('1 compte(s), 1 message(s) non lu(s).')
        ->assertSuccessful();
});

it('reports accounts without a Maildir/new directory', function (): void {
    fakeLdapReturning([ldapEntry('jdoe', $this->home.'/nope')]);

    $this->artisan('citoyen:new-mail')
        ->expectsOutputToContain('pas de Maildir/new')
        ->assertSuccessful();
});

it('hides accounts without unread messages when --only-with-mail is given', function (): void {
    fakeLdapReturning([ldapEntry('jdoe', $this->home)]);

    $this->artisan('citoyen:new-mail', ['--only-with-mail' => true])
        ->expectsOutputToContain('Aucun compte avec des messages non lus.')
        ->assertSuccessful();
});

it('fails when no account matches the keyword', function (): void {
    fakeLdapReturning([]);

    $this->artisan('citoyen:new-mail', ['keyword' => 'inconnu'])
        ->expectsOutputToContain('Aucun compte trouvé pour inconnu')
        ->assertFailed();
});
