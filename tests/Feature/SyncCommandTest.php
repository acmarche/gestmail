<?php

declare(strict_types=1);

use App\Ldap\LdapCitoyenRepository;
use App\Models\Citoyen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

beforeEach(function (): void {
    $this->home = sys_get_temp_dir().'/gestmail-sync-'.uniqid();
    File::makeDirectory($this->home.'/Maildir', 0755, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->home);
    DirectoryFake::tearDown();
});

/**
 * Entrée d'annuaire sans attribut `mail`, ignorée par la boucle d'import.
 *
 * @return array{dn: array<string>, uid: array<string>}
 */
function syncLdapEntryWithoutMail(string $uid): array
{
    return [
        'dn' => ["uid={$uid},ou=Users,ou=Citoyens,dc=marche,dc=be"],
        'uid' => [$uid],
    ];
}

function makeSyncCitoyen(string $uid): Citoyen
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

/**
 * Entrée d'annuaire complète, acceptée par la boucle d'import.
 *
 * @return array<string, array<string>>
 */
function scanLdapEntry(string $uid, string $homeDirectory): array
{
    return [
        'dn' => ["uid={$uid},ou=Users,ou=Citoyens,dc=marche,dc=be"],
        'uid' => [$uid],
        'mail' => [$uid.'@marche.be'],
        'homeDirectory' => [$homeDirectory],
        'gosaMailQuota' => ['250'],
        'gosaMailForwardingAddress' => [$uid.'@marche.be'],
    ];
}

/**
 * `handle()` déclenche deux recherches LDAP : l'import puis la purge.
 *
 * @param  array<int, array<string, array<string>>>  $entries
 */
function fakeCitoyenDirectory(array $entries): void
{
    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn($entries),
            LdapFake::operation('search')->andReturn($entries),
        ]);
}

it('deletes SQL citizens that no longer exist in the directory', function (): void {
    $entries = collect(range(1, 201))
        ->map(fn (int $i): array => syncLdapEntryWithoutMail('user'.$i))
        ->all();

    makeSyncCitoyen('user1');
    makeSyncCitoyen('ghost');

    fakeCitoyenDirectory($entries);

    $this->artisan('citoyen:sync')
        ->doesntExpectOutputToContain('Removed from citoyen user1')
        ->expectsOutputToContain('Removed from citoyen ghost')
        ->assertSuccessful();

    expect(Citoyen::query()->where('uid', 'ghost')->exists())->toBeFalse()
        ->and(Citoyen::query()->where('uid', 'user1')->exists())->toBeTrue();
});

it('deletes nothing when the directory returns 200 entries or fewer', function (): void {
    $entries = collect(range(1, 200))
        ->map(fn (int $i): array => syncLdapEntryWithoutMail('user'.$i))
        ->all();

    makeSyncCitoyen('ghost');

    fakeCitoyenDirectory($entries);

    $this->artisan('citoyen:sync')
        ->doesntExpectOutputToContain('Removed from citoyen')
        ->assertSuccessful();

    expect(Citoyen::query()->where('uid', 'ghost')->exists())->toBeTrue();
});

it('reads the last login from the Dovecot index log', function (): void {
    $connectedAt = CarbonImmutable::parse('2026-07-14 09:30:00');
    File::put($this->home.'/Maildir/dovecot.index.log', 'binary');
    touch($this->home.'/Maildir/dovecot.index.log', $connectedAt->getTimestamp());

    $lastLoginAt = (new LdapCitoyenRepository)->lastLoginAt($this->home);

    expect($lastLoginAt?->getTimestamp())->toBe($connectedAt->getTimestamp());
});

it('falls back to the cur directory when no index log exists', function (): void {
    $readAt = CarbonImmutable::parse('2026-06-01 08:00:00');
    File::makeDirectory($this->home.'/Maildir/cur', 0755, true);
    touch($this->home.'/Maildir/cur', $readAt->getTimestamp());

    $lastLoginAt = (new LdapCitoyenRepository)->lastLoginAt($this->home);

    expect($lastLoginAt?->getTimestamp())->toBe($readAt->getTimestamp());
});

it('returns no last login when the Maildir holds no Dovecot trace', function (): void {
    expect((new LdapCitoyenRepository)->lastLoginAt($this->home))->toBeNull()
        ->and((new LdapCitoyenRepository)->lastLoginAt(null))->toBeNull();
});

it('stores the last connection date on the synced citizen', function (): void {
    $connectedAt = CarbonImmutable::parse('2026-05-20 12:00:00');
    File::put($this->home.'/Maildir/dovecot.index.log', 'binary');
    touch($this->home.'/Maildir/dovecot.index.log', $connectedAt->getTimestamp());

    $citoyen = makeSyncCitoyen('jdoe');
    $citoyen->update(['homeDirectory' => $this->home]);

    fakeCitoyenDirectory([[
        'dn' => ['uid=jdoe,ou=Users,ou=Citoyens,dc=marche,dc=be'],
        'uid' => ['jdoe'],
        'mail' => ['jdoe@marche.be'],
        'homeDirectory' => [$this->home],
        'gosaMailQuota' => ['250'],
        'gosaMailForwardingAddress' => ['jdoe@marche.be'],
    ]]);

    $this->artisan('citoyen:sync')->assertSuccessful();

    expect($citoyen->refresh()->last_connection->toDateString())->toBe('2026-05-20');
});

/**
 * Écrit un fichier de quota Maildir++ : ligne de limite puis lignes de deltas.
 *
 * @param  array<int, int>  $deltas
 */
function writeMaildirSize(string $homeDirectory, array $deltas): void
{
    File::ensureDirectoryExists($homeDirectory.'/Maildir');
    File::put(
        $homeDirectory.'/Maildir/maildirsize',
        "10485760S,1000C\n".implode("\n", array_map(fn (int $b): string => $b.' 1', $deltas))."\n"
    );
}

it('lists missing IMAP directories and totals the space used', function (): void {
    $withMailbox = $this->home.'/withmail';
    writeMaildirSize($withMailbox, [1024, 2048]);

    makeSyncCitoyen('withmail')->update(['homeDirectory' => $withMailbox]);
    makeSyncCitoyen('nomail')->update(['homeDirectory' => $this->home.'/does-not-exist']);

    fakeCitoyenDirectory([
        scanLdapEntry('withmail', $withMailbox),
        scanLdapEntry('nomail', $this->home.'/does-not-exist'),
    ]);

    $this->artisan('citoyen:sync', ['--scan-imap' => true])
        ->expectsOutputToContain('1 répertoire(s) IMAP introuvable(s)')
        ->expectsOutputToContain('- nomail')
        ->expectsOutputToContain('1 répertoire(s) IMAP analysé(s), espace occupé : 3.00 KB')
        ->assertSuccessful();
});

it('separates directories that carry no maildirsize file', function (): void {
    $withoutQuotaFile = $this->home.'/noquota';
    File::makeDirectory($withoutQuotaFile.'/Maildir/cur', 0755, true);

    makeSyncCitoyen('noquota')->update(['homeDirectory' => $withoutQuotaFile]);

    fakeCitoyenDirectory([scanLdapEntry('noquota', $withoutQuotaFile)]);

    $this->artisan('citoyen:sync', ['--scan-imap' => true])
        ->doesntExpectOutputToContain('introuvable')
        ->expectsOutputToContain('1 répertoire(s) IMAP sans fichier maildirsize')
        ->expectsOutputToContain('- noquota')
        ->expectsOutputToContain('0 répertoire(s) IMAP analysé(s)')
        ->assertSuccessful();
});

it('says nothing about IMAP directories without the option', function (): void {
    makeSyncCitoyen('withmail')->update(['homeDirectory' => $this->home.'/does-not-exist']);

    fakeCitoyenDirectory([scanLdapEntry('withmail', $this->home.'/does-not-exist')]);

    $this->artisan('citoyen:sync')
        ->doesntExpectOutputToContain('IMAP')
        ->assertSuccessful();
});

it('sums the maildirsize deltas, deletions included', function (): void {
    writeMaildirSize($this->home, [5000, 3000, -2000]);

    expect((new LdapCitoyenRepository)->mailboxSize($this->home))->toBe(6000);
});

it('ignores the quota definition line and malformed rows', function (): void {
    File::put($this->home.'/Maildir/maildirsize', "10485760S,1000C\n1500 1\ngarbage\n\n500 1\n");

    expect((new LdapCitoyenRepository)->mailboxSize($this->home))->toBe(2000);
});

it('never reports a negative mailbox size', function (): void {
    writeMaildirSize($this->home, [1000, -4000]);

    expect((new LdapCitoyenRepository)->mailboxSize($this->home))->toBe(0);
});

it('returns no size when maildirsize is absent', function (): void {
    expect((new LdapCitoyenRepository)->mailboxSize($this->home))->toBeNull()
        ->and((new LdapCitoyenRepository)->mailboxSize(null))->toBeNull();
});

it('detects whether the IMAP directory exists', function (): void {
    $repository = new LdapCitoyenRepository;

    expect($repository->hasMailbox($this->home))->toBeTrue()
        ->and($repository->hasMailbox($this->home.'/nope'))->toBeFalse()
        ->and($repository->hasMailbox(null))->toBeFalse();
});
