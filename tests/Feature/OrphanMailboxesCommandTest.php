<?php

declare(strict_types=1);

use App\Models\Citoyen;
use App\Repository\MailboxRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/gestmail-orphans-'.uniqid();

    $repository = new MailboxRepository;
    $repository->mailRoot = $this->root;
    $this->app->instance(MailboxRepository::class, $repository);
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

function makeOrphanCitoyen(string $mail, ?string $homeDirectory = null): Citoyen
{
    return Citoyen::factory()->create([
        'uid' => Str::before($mail, '@'),
        'homeDirectory' => $homeDirectory ?? '/nonexistent/'.$mail,
    ]);
}

/**
 * Comptes de remplissage pour dépasser le seuil d'analyse des répertoires IMAP.
 */
function fillCitoyensTable(): void
{
    foreach (range(1, 200) as $i) {
        makeOrphanCitoyen("filler{$i}@marche.be");
    }
}

/**
 * Crée un répertoire IMAP `<racine>/<initiale>/<adresse>/Maildir` et son fichier de quota.
 *
 * @param  array<int, int>  $deltas
 */
function makeMailboxDirectory(string $root, string $name, array $deltas = [1024]): string
{
    $path = $root.'/'.mb_substr($name, 0, 1).'/'.$name;
    File::ensureDirectoryExists($path.'/Maildir');

    if ($deltas !== []) {
        File::put(
            $path.'/Maildir/maildirsize',
            "10485760S,1000C\n".implode("\n", array_map(fn (int $b): string => $b.' 1', $deltas))."\n"
        );
    }

    return $path;
}

it('lists IMAP directories that have no citizen account', function (): void {
    makeMailboxDirectory($this->root, 'xavier.collart@marche.be', [1024]);
    makeMailboxDirectory($this->root, 'xavier.gosseye@marche.be', [2048, 1024]);

    makeOrphanCitoyen('xavier.collart@marche.be');
    fillCitoyensTable();

    $this->artisan('citoyen:orphan-mailboxes')
        ->expectsOutputToContain('répertoire(s) IMAP examiné(s)')
        ->expectsOutputToContain('xavier.gosseye')
        ->expectsOutputToContain('1 répertoire(s) IMAP sans compte citoyen, 3.00 KB')
        ->assertSuccessful();
});

it('keeps a citizen whose directory exists, whatever its case', function (): void {
    makeMailboxDirectory($this->root, 'Xavier.Collart@marche.be');

    makeOrphanCitoyen('xavier.collart@marche.be');
    fillCitoyensTable();

    $this->artisan('citoyen:orphan-mailboxes')
        ->expectsOutputToContain('Aucun répertoire orphelin.')
        ->doesntExpectOutputToContain('sans compte citoyen')
        ->assertSuccessful();
});

it('abandons the scan when the citoyens table looks truncated', function (): void {
    makeMailboxDirectory($this->root, 'orphan.account@marche.be');
    makeOrphanCitoyen('jdoe@marche.be');

    $this->artisan('citoyen:orphan-mailboxes')
        ->expectsOutputToContain('Table citoyens incomplète (1 comptes)')
        ->doesntExpectOutputToContain('orphan.account')
        ->assertSuccessful();
});

it('deletes orphan directories with --delete and spares the others', function (): void {
    $kept = makeMailboxDirectory($this->root, 'xavier.collart@marche.be', [1024]);
    $orphan = makeMailboxDirectory($this->root, 'xavier.gosseye@marche.be', [2048, 1024]);

    makeOrphanCitoyen('xavier.collart@marche.be');
    fillCitoyensTable();

    $this->artisan('citoyen:orphan-mailboxes', ['--delete' => true])
        ->expectsOutputToContain('Supprimé : '.$orphan)
        ->expectsOutputToContain('1 répertoire(s) supprimé(s), 3.00 KB libéré(s).')
        ->assertSuccessful();

    expect(File::isDirectory($orphan))->toBeFalse()
        ->and(File::isDirectory($kept))->toBeTrue();
});

it('deletes nothing without --delete', function (): void {
    $orphan = makeMailboxDirectory($this->root, 'xavier.gosseye@marche.be');

    makeOrphanCitoyen('xavier.collart@marche.be');
    fillCitoyensTable();

    $this->artisan('citoyen:orphan-mailboxes')
        ->expectsOutputToContain('Aucune suppression effectuée')
        ->assertSuccessful();

    expect(File::isDirectory($orphan))->toBeTrue();
});

it('deletes nothing when the citoyens table looks truncated', function (): void {
    $orphan = makeMailboxDirectory($this->root, 'orphan.account@marche.be');
    makeOrphanCitoyen('jdoe@marche.be');

    $this->artisan('citoyen:orphan-mailboxes', ['--delete' => true])
        ->expectsOutputToContain('Table citoyens incomplète (1 comptes)')
        ->doesntExpectOutputToContain('Supprimé')
        ->assertSuccessful();

    expect(File::isDirectory($orphan))->toBeTrue();
});
