<?php

declare(strict_types=1);

use App\Ldap\LdapCitoyenRepository;
use Carbon\CarbonImmutable;
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

it('reads the delivery date from the Maildir filename', function (): void {
    $deliveredAt = CarbonImmutable::now()->subDays(40)->startOfSecond();
    File::put($this->home.'/Maildir/new/'.$deliveredAt->getTimestamp().'.M1P2.mail', 'body');

    expect(app(LdapCitoyenRepository::class)->oldestNewMailAt($this->home))
        ->toEqual($deliveredAt);
});

it('keeps the oldest message when several are waiting', function (): void {
    $oldest = CarbonImmutable::now()->subDays(90)->startOfSecond();
    $recent = CarbonImmutable::now()->subDays(2)->startOfSecond();
    File::put($this->home.'/Maildir/new/'.$recent->getTimestamp().'.M1P2.mail', 'body');
    File::put($this->home.'/Maildir/new/'.$oldest->getTimestamp().'.M3P4.mail', 'body');

    expect(app(LdapCitoyenRepository::class)->oldestNewMailAt($this->home))
        ->toEqual($oldest);
});

it('falls back on the file mtime when the filename is not a Maildir name', function (): void {
    $modifiedAt = CarbonImmutable::now()->subDays(10)->startOfSecond();
    File::put($this->home.'/Maildir/new/not-a-maildir-name', 'body');
    touch($this->home.'/Maildir/new/not-a-maildir-name', $modifiedAt->getTimestamp());

    expect(app(LdapCitoyenRepository::class)->oldestNewMailAt($this->home))
        ->toEqual($modifiedAt);
});

it('returns no date when Maildir/new is empty or missing', function (): void {
    $repository = app(LdapCitoyenRepository::class);

    expect($repository->oldestNewMailAt($this->home))->toBeNull()
        ->and($repository->oldestNewMailAt($this->home.'/nope'))->toBeNull();
});

it('shows how long the messages have been waiting', function (): void {
    $deliveredAt = CarbonImmutable::now()->subDays(45);
    File::put($this->home.'/Maildir/new/'.$deliveredAt->getTimestamp().'.M1P2.mail', 'body');
    fakeLdapReturning([ldapEntry('jdoe', $this->home)]);

    $this->artisan('citoyen:new-mail')
        ->expectsOutputToContain($deliveredAt->format('d/m/Y'))
        ->assertSuccessful();
});

it('keeps only the accounts waiting longer than --min-days', function (): void {
    $stale = sys_get_temp_dir().'/gestmail-stale-'.uniqid();
    File::makeDirectory($stale.'/Maildir/new', 0755, true);
    File::put($stale.'/Maildir/new/'.CarbonImmutable::now()->subDays(120)->getTimestamp().'.M1P2.mail', 'body');
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(3)->getTimestamp().'.M3P4.mail', 'body');

    fakeLdapReturning([
        ldapEntry('jdoe', $this->home),
        ldapEntry('asmith', $stale),
    ]);

    $this->artisan('citoyen:new-mail', ['--min-days' => 30])
        ->expectsOutputToContain('asmith@marche.be')
        ->doesntExpectOutputToContain('jdoe@marche.be')
        ->assertSuccessful();

    File::deleteDirectory($stale);
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
        ->expectsOutputToContain('Aucun compte correspondant.')
        ->assertSuccessful();
});

it('fails when no account matches the keyword', function (): void {
    fakeLdapReturning([]);

    $this->artisan('citoyen:new-mail', ['keyword' => 'inconnu'])
        ->expectsOutputToContain('Aucun compte trouvé pour inconnu')
        ->assertFailed();
});
