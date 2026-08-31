<?php

declare(strict_types=1);

use App\Ldap\LdapCitoyenRepository;
use App\Models\Citoyen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

beforeEach(function (): void {
    $this->home = sys_get_temp_dir().'/gestmail-test-'.uniqid();
    File::makeDirectory($this->home.'/Maildir/new', 0755, true);

    $this->sieveRoot = sys_get_temp_dir().'/gestmail-sieve-'.uniqid().'/';
    $repository = new LdapCitoyenRepository;
    $repository->sieveRoot = $this->sieveRoot;
    $this->app->instance(LdapCitoyenRepository::class, $repository);
});

afterEach(function (): void {
    File::deleteDirectory($this->home);
    File::deleteDirectory(mb_rtrim($this->sieveRoot, '/'));
    DirectoryFake::tearDown();
});

/**
 * Écrit un script Sieve pour un uid, à l'emplacement attendu par le dépôt.
 */
function writeSieveScript(string $uid, string $content): void
{
    $path = test()->sieveRoot.mb_substr($uid, 0, 1).'/'.$uid.'/sieve';
    File::makeDirectory($path, 0755, true);
    File::put($path.'/actif.sieve', $content);
}

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
 * Crée l'entrée SQL correspondant à un citoyen de l'annuaire.
 */
function createCitoyen(string $uid): Citoyen
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

it('finds the redirect addresses of a sieve script', function (): void {
    writeSieveScript('jdoe', <<<'SIEVE'
    require ["fileinto"];
    # redirect "commente@example.be";
    if header :contains "subject" "facture" {
        redirect :copy "compta@marche.be";
    }
    redirect "perso@gmail.com";
    stop;
    SIEVE);

    expect(app(LdapCitoyenRepository::class)->sieveRedirects('jdoe'))
        ->toBe(['compta@marche.be', 'perso@gmail.com']);
});

it('returns no redirect when the citizen has no sieve script', function (): void {
    expect(app(LdapCitoyenRepository::class)->sieveRedirects('jdoe'))->toBe([]);
});

it('skips the accounts whose sieve script redirects, without proposing any deletion', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');
    writeSieveScript('jdoe', 'redirect "perso@gmail.com";');
    fakeLdapReturning([ldapEntry('jdoe', $this->home)]);

    $this->artisan('citoyen:new-mail', ['--delete' => true])
        ->expectsOutputToContain('Script(s) Sieve : actif.sieve')
        ->expectsOutputToContain('Redirection active vers : perso@gmail.com')
        ->expectsOutputToContain('Compte jdoe ignoré')
        ->doesntExpectOutputToContain('Supprimer le compte')
        ->expectsOutputToContain('0 compte(s) supprimé(s), 0 conservé(s), 1 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('proposes the deletion of an account whose sieve script does not redirect', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');
    writeSieveScript('jdoe', 'require ["fileinto"]; fileinto "INBOX.Junk";');
    fakeLdapReturning([ldapEntry('jdoe', $this->home)]);

    $this->artisan('citoyen:new-mail', ['--delete' => true])
        ->expectsOutputToContain('Script(s) Sieve : actif.sieve')
        ->expectsOutputToContain('Aucune redirection trouvée.')
        ->expectsConfirmation('Supprimer le compte jdoe ?', 'no')
        ->expectsOutputToContain('Suppression refusée')
        ->expectsOutputToContain('0 compte(s) supprimé(s), 1 conservé(s), 0 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('deletes an account without any sieve script, without asking for confirmation', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([ldapEntry('jdoe', $this->home)]),
            LdapFake::operation('delete')->once()->andReturn(true),
        ]);

    $this->artisan('citoyen:new-mail', ['--delete' => true])
        ->expectsOutputToContain('Aucun script Sieve : aucune redirection possible, suppression directe.')
        ->doesntExpectOutputToContain('Supprimer le compte')
        ->expectsOutputToContain("Compte jdoe supprimé de l'annuaire LDAP.")
        ->expectsOutputToContain('rm -rI '.$this->home)
        ->expectsOutputToContain('1 compte(s) supprimé(s), 0 conservé(s), 0 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('deletes the SQL citizen along with the LDAP entry', function (): void {
    createCitoyen('jdoe');
    $kept = createCitoyen('asmith');
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([ldapEntry('jdoe', $this->home)]),
            LdapFake::operation('delete')->once()->andReturn(true),
        ]);

    $this->artisan('citoyen:new-mail', ['--delete' => true])
        ->expectsOutputToContain('Entrée SQL supprimée pour jdoe.')
        ->assertSuccessful();

    expect(Citoyen::query()->where('uid', 'jdoe')->exists())->toBeFalse()
        ->and(Citoyen::query()->whereKey($kept->getKey())->exists())->toBeTrue();
});

it('reports when the deleted LDAP account has no SQL counterpart', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([ldapEntry('jdoe', $this->home)]),
            LdapFake::operation('delete')->once()->andReturn(true),
        ]);

    $this->artisan('citoyen:new-mail', ['--delete' => true])
        ->expectsOutputToContain('Aucune entrée SQL trouvée pour jdoe.')
        ->assertSuccessful();
});

it('deletes the confirmed account whose sieve script does not redirect', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');
    writeSieveScript('jdoe', 'require ["fileinto"]; fileinto "INBOX.Junk";');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([ldapEntry('jdoe', $this->home)]),
            LdapFake::operation('delete')->once()->andReturn(true),
        ]);

    $this->artisan('citoyen:new-mail', ['--delete' => true])
        ->expectsOutputToContain('Aucune redirection trouvée.')
        ->expectsConfirmation('Supprimer le compte jdoe ?', 'yes')
        ->expectsOutputToContain("Compte jdoe supprimé de l'annuaire LDAP.")
        ->expectsOutputToContain('1 compte(s) supprimé(s), 0 conservé(s), 0 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('does not propose any deletion without the --delete option', function (): void {
    File::put($this->home.'/Maildir/new/1234567.mail', 'body');
    fakeLdapReturning([ldapEntry('jdoe', $this->home)]);

    $this->artisan('citoyen:new-mail')
        ->doesntExpectOutputToContain('Supprimer le compte')
        ->assertSuccessful();
});

it('recaps every processed account in a table at the end', function (): void {
    $kept = sys_get_temp_dir().'/gestmail-kept-'.uniqid();
    $redirected = sys_get_temp_dir().'/gestmail-redirected-'.uniqid();
    foreach ([$kept, $redirected] as $home) {
        File::makeDirectory($home.'/Maildir/new', 0755, true);
        File::put($home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');
    }
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M3P4.mail', 'body');

    writeSieveScript('asmith', 'require ["fileinto"]; fileinto "INBOX.Junk";');
    writeSieveScript('bjones', 'redirect "perso@gmail.com";');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([
                ldapEntry('jdoe', $this->home),
                ldapEntry('asmith', $kept),
                ldapEntry('bjones', $redirected),
            ]),
            LdapFake::operation('delete')->once()->andReturn(true),
        ]);

    $this->artisan('citoyen:new-mail', ['--delete' => true])
        ->expectsConfirmation('Supprimer le compte asmith ?', 'no')
        ->expectsOutputToContain('statut')
        ->expectsOutputToContain('Supprimé')
        ->expectsOutputToContain('Conservé')
        ->expectsOutputToContain('Ignoré')
        ->expectsOutputToContain('1 compte(s) supprimé(s), 1 conservé(s), 1 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();

    File::deleteDirectory($kept);
    File::deleteDirectory($redirected);
});
