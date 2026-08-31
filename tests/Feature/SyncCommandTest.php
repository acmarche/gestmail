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

/**
 * Crée un répertoire IMAP `<racine>/<initiale>/<uid>/Maildir` et son fichier de quota.
 *
 * @param  array<int, int>  $deltas
 */
function makeMailboxDirectory(string $root, string $uid, array $deltas = [1024]): string
{
    $path = $root.'/'.mb_substr($uid, 0, 1).'/'.$uid;
    File::ensureDirectoryExists($path.'/Maildir');

    if ($deltas !== []) {
        File::put(
            $path.'/Maildir/maildirsize',
            "10485760S,1000C\n".implode("\n", array_map(fn (int $b): string => $b.' 1', $deltas))."\n"
        );
    }

    return $path;
}

/**
 * Annuaire d'au moins 200 entrées, seuil en deçà duquel l'analyse est abandonnée.
 *
 * @param  array<int, string>  $uids
 * @return array<int, array<string, array<string>>>
 */
function paddedDirectory(array $uids): array
{
    $entries = array_map(fn (string $uid): array => scanLdapEntry($uid, '/nonexistent/'.$uid), $uids);

    foreach (range(1, 200) as $i) {
        $entries[] = scanLdapEntry('filler'.$i, '/nonexistent/filler'.$i);
    }

    return $entries;
}

it('lists IMAP directories that have no LDAP entry', function (): void {
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->home.'/mail';
    $this->app->instance(LdapCitoyenRepository::class, $repository);

    makeMailboxDirectory($this->home.'/mail', 'xavier.collart', [1024]);
    makeMailboxDirectory($this->home.'/mail', 'xavier.gosseye', [2048, 1024]);

    fakeCitoyenDirectory(paddedDirectory(['xavier.collart']));

    $this->artisan('citoyen:sync', ['--scan-imap' => true])
        ->expectsOutputToContain('répertoire(s) IMAP examiné(s)')
        ->expectsOutputToContain('xavier.gosseye')
        ->expectsOutputToContain('1 répertoire(s) IMAP sans entrée LDAP, 3.00 KB')
        ->assertSuccessful();
});

it('keeps an LDAP account whose directory exists', function (): void {
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->home.'/mail';
    $this->app->instance(LdapCitoyenRepository::class, $repository);

    makeMailboxDirectory($this->home.'/mail', 'xavier.collart');

    fakeCitoyenDirectory(paddedDirectory(['xavier.collart']));

    $this->artisan('citoyen:sync', ['--scan-imap' => true])
        ->expectsOutputToContain('Aucun répertoire orphelin.')
        ->doesntExpectOutputToContain('sans entrée LDAP')
        ->assertSuccessful();
});

it('abandons the scan when the directory read looks truncated', function (): void {
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->home.'/mail';
    $this->app->instance(LdapCitoyenRepository::class, $repository);

    makeMailboxDirectory($this->home.'/mail', 'orphan.account');

    fakeCitoyenDirectory([scanLdapEntry('jdoe', '/nonexistent/jdoe')]);

    $this->artisan('citoyen:sync', ['--scan-imap' => true])
        ->expectsOutputToContain('Annuaire incomplet (1 entrées)')
        ->doesntExpectOutputToContain('orphan.account')
        ->assertSuccessful();
});

it('deletes orphan directories with --delete and spares the others', function (): void {
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->home.'/mail';
    $this->app->instance(LdapCitoyenRepository::class, $repository);

    $kept = makeMailboxDirectory($this->home.'/mail', 'xavier.collart', [1024]);
    $orphan = makeMailboxDirectory($this->home.'/mail', 'xavier.gosseye', [2048, 1024]);

    fakeCitoyenDirectory(paddedDirectory(['xavier.collart']));

    $this->artisan('citoyen:sync', ['--delete' => true])
        ->expectsOutputToContain('Supprimé : '.$orphan)
        ->expectsOutputToContain('1 répertoire(s) supprimé(s), 3.00 KB libéré(s).')
        ->assertSuccessful();

    expect(File::isDirectory($orphan))->toBeFalse()
        ->and(File::isDirectory($kept))->toBeTrue();
});

it('deletes nothing when --scan-imap is used alone', function (): void {
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->home.'/mail';
    $this->app->instance(LdapCitoyenRepository::class, $repository);

    $orphan = makeMailboxDirectory($this->home.'/mail', 'xavier.gosseye');

    fakeCitoyenDirectory(paddedDirectory(['xavier.collart']));

    $this->artisan('citoyen:sync', ['--scan-imap' => true])
        ->expectsOutputToContain('Aucune suppression effectuée')
        ->assertSuccessful();

    expect(File::isDirectory($orphan))->toBeTrue();
});

it('deletes nothing when the directory read looks truncated', function (): void {
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->home.'/mail';
    $this->app->instance(LdapCitoyenRepository::class, $repository);

    $orphan = makeMailboxDirectory($this->home.'/mail', 'orphan.account');

    fakeCitoyenDirectory([scanLdapEntry('jdoe', '/nonexistent/jdoe')]);

    $this->artisan('citoyen:sync', ['--delete' => true])
        ->expectsOutputToContain('Annuaire incomplet (1 entrées)')
        ->doesntExpectOutputToContain('Supprimé')
        ->assertSuccessful();

    expect(File::isDirectory($orphan))->toBeTrue();
});

it('says nothing about IMAP directories without the option', function (): void {
    makeSyncCitoyen('withmail')->update(['homeDirectory' => $this->home.'/does-not-exist']);

    fakeCitoyenDirectory([scanLdapEntry('withmail', $this->home.'/does-not-exist')]);

    $this->artisan('citoyen:sync')
        ->doesntExpectOutputToContain('IMAP')
        ->assertSuccessful();
});

it('collects mailbox directories grouped by initial', function (): void {
    $root = $this->home.'/mail';
    makeMailboxDirectory($root, 'xavier.collart');
    makeMailboxDirectory($root, 'anne.dupont');
    File::ensureDirectoryExists($root.'/Maildir/cur');
    File::ensureDirectoryExists($root.'/x/not-a-mailbox');

    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $root;

    expect(array_keys($repository->allMailboxDirectories()))
        ->toBe(['anne.dupont', 'xavier.collart']);
});

it('returns no mailbox directory when the root is absent', function (): void {
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->home.'/nowhere';

    expect($repository->allMailboxDirectories())->toBe([]);
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
