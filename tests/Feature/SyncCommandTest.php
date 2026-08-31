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
