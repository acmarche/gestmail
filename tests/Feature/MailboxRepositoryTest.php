<?php

declare(strict_types=1);

use App\Repository\MailboxRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->home = sys_get_temp_dir().'/gestmail-mailbox-'.uniqid();
    File::makeDirectory($this->home.'/Maildir/new', 0755, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->home);
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
 * Écrit un script Sieve dans le répertoire d'un citoyen.
 */
function writeRepositorySieveScript(string $homeDirectory, string $content): void
{
    File::makeDirectory($homeDirectory.'/sieve', 0755, true);
    File::put($homeDirectory.'/sieve/actif.sieve', $content);
}

/**
 * Crée un répertoire IMAP `<racine>/<initiale>/<adresse>/Maildir` et son fichier de quota.
 *
 * @param  array<int, int>  $deltas
 */
function makeRepositoryMailbox(string $root, string $name, array $deltas = [1024]): string
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

it('reads the last login from the Dovecot index log', function (): void {
    $connectedAt = CarbonImmutable::parse('2026-07-14 09:30:00');
    File::put($this->home.'/Maildir/dovecot.index.log', 'binary');
    touch($this->home.'/Maildir/dovecot.index.log', $connectedAt->getTimestamp());

    $lastLoginAt = (new MailboxRepository)->lastLoginAt($this->home);

    expect($lastLoginAt?->getTimestamp())->toBe($connectedAt->getTimestamp());
});

it('falls back to the cur directory when no index log exists', function (): void {
    $readAt = CarbonImmutable::parse('2026-06-01 08:00:00');
    File::makeDirectory($this->home.'/Maildir/cur', 0755, true);
    touch($this->home.'/Maildir/cur', $readAt->getTimestamp());

    $lastLoginAt = (new MailboxRepository)->lastLoginAt($this->home);

    expect($lastLoginAt?->getTimestamp())->toBe($readAt->getTimestamp());
});

it('returns no last login when the Maildir holds no Dovecot trace', function (): void {
    expect((new MailboxRepository)->lastLoginAt($this->home))->toBeNull()
        ->and((new MailboxRepository)->lastLoginAt(null))->toBeNull();
});

it('collects mailbox directories grouped by initial', function (): void {
    $root = $this->home.'/mail';
    makeRepositoryMailbox($root, 'xavier.collart@marche.be');
    makeRepositoryMailbox($root, 'anne.dupont@marche.be');
    File::ensureDirectoryExists($root.'/Maildir/cur');
    File::ensureDirectoryExists($root.'/x/not-a-mailbox');

    $repository = new MailboxRepository;
    $repository->mailRoot = $root;

    expect(array_keys($repository->allMailboxDirectories()))
        ->toBe(['anne.dupont@marche.be', 'xavier.collart@marche.be']);
});

it('returns no mailbox directory when the root is absent', function (): void {
    $repository = new MailboxRepository;
    $repository->mailRoot = $this->home.'/nowhere';

    expect($repository->allMailboxDirectories())->toBe([]);
});

it('sums the maildirsize deltas, deletions included', function (): void {
    writeMaildirSize($this->home, [5000, 3000, -2000]);

    expect((new MailboxRepository)->mailboxSize($this->home))->toBe(6000);
});

it('ignores the quota definition line and malformed rows', function (): void {
    File::put($this->home.'/Maildir/maildirsize', "10485760S,1000C\n1500 1\ngarbage\n\n500 1\n");

    expect((new MailboxRepository)->mailboxSize($this->home))->toBe(2000);
});

it('never reports a negative mailbox size', function (): void {
    writeMaildirSize($this->home, [1000, -4000]);

    expect((new MailboxRepository)->mailboxSize($this->home))->toBe(0);
});

it('returns no size when maildirsize is absent', function (): void {
    expect((new MailboxRepository)->mailboxSize($this->home))->toBeNull()
        ->and((new MailboxRepository)->mailboxSize(null))->toBeNull();
});

it('counts the messages present in Maildir/new', function (): void {
    File::put($this->home.'/Maildir/new/1234567.mail', 'body');
    File::put($this->home.'/Maildir/new/1234568.mail', 'body');

    expect(app(MailboxRepository::class)->countNewMails($this->home))->toBe(2);
});

it('returns null when the Maildir/new directory does not exist', function (): void {
    $repository = app(MailboxRepository::class);

    expect($repository->countNewMails($this->home.'/nope'))->toBeNull()
        ->and($repository->countNewMails(null))->toBeNull();
});

it('reads the delivery date from the Maildir filename', function (): void {
    $deliveredAt = CarbonImmutable::now()->subDays(40)->startOfSecond();
    File::put($this->home.'/Maildir/new/'.$deliveredAt->getTimestamp().'.M1P2.mail', 'body');

    expect(app(MailboxRepository::class)->oldestNewMailAt($this->home))
        ->toEqual($deliveredAt);
});

it('keeps the oldest message when several are waiting', function (): void {
    $oldest = CarbonImmutable::now()->subDays(90)->startOfSecond();
    $recent = CarbonImmutable::now()->subDays(2)->startOfSecond();
    File::put($this->home.'/Maildir/new/'.$recent->getTimestamp().'.M1P2.mail', 'body');
    File::put($this->home.'/Maildir/new/'.$oldest->getTimestamp().'.M3P4.mail', 'body');

    expect(app(MailboxRepository::class)->oldestNewMailAt($this->home))
        ->toEqual($oldest);
});

it('falls back on the file mtime when the filename is not a Maildir name', function (): void {
    $modifiedAt = CarbonImmutable::now()->subDays(10)->startOfSecond();
    File::put($this->home.'/Maildir/new/not-a-maildir-name', 'body');
    touch($this->home.'/Maildir/new/not-a-maildir-name', $modifiedAt->getTimestamp());

    expect(app(MailboxRepository::class)->oldestNewMailAt($this->home))
        ->toEqual($modifiedAt);
});

it('returns no date when Maildir/new is empty or missing', function (): void {
    $repository = app(MailboxRepository::class);

    expect($repository->oldestNewMailAt($this->home))->toBeNull()
        ->and($repository->oldestNewMailAt($this->home.'/nope'))->toBeNull();
});

it('finds the redirect addresses of a sieve script', function (): void {
    writeRepositorySieveScript($this->home, <<<'SIEVE'
    require ["fileinto"];
    # redirect "commente@example.be";
    if header :contains "subject" "facture" {
        redirect :copy "compta@marche.be";
    }
    redirect "perso@gmail.com";
    stop;
    SIEVE);

    expect(app(MailboxRepository::class)->sieveRedirects($this->home))
        ->toBe(['compta@marche.be', 'perso@gmail.com']);
});

it('returns no redirect when the citizen has no sieve script', function (): void {
    expect(app(MailboxRepository::class)->sieveRedirects($this->home))->toBe([]);
});
