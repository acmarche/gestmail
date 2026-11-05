<?php

declare(strict_types=1);

use App\Models\Citoyen;
use App\Models\VirtualUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->home = sys_get_temp_dir().'/gestmail-test-'.uniqid();
    File::makeDirectory($this->home.'/Maildir/new', 0755, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->home);
});

/**
 * Écrit un script Sieve dans le répertoire d'un citoyen.
 */
function writeSieveScript(string $homeDirectory, string $content): void
{
    File::makeDirectory($homeDirectory.'/sieve', 0755, true);
    File::put($homeDirectory.'/sieve/actif.sieve', $content);
}

function createCitoyen(string $uid, string $homeDirectory): Citoyen
{
    return Citoyen::factory()->create(['uid' => $uid, 'homeDirectory' => $homeDirectory]);
}

it('shows how long the messages have been waiting', function (): void {
    $deliveredAt = CarbonImmutable::now()->subDays(45);
    File::put($this->home.'/Maildir/new/'.$deliveredAt->getTimestamp().'.M1P2.mail', 'body');
    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail')
        ->expectsOutputToContain($deliveredAt->format('d/m/Y'))
        ->assertSuccessful();
});

it('keeps only the accounts waiting longer than --min-days', function (): void {
    $stale = sys_get_temp_dir().'/gestmail-stale-'.uniqid();
    File::makeDirectory($stale.'/Maildir/new', 0755, true);
    File::put($stale.'/Maildir/new/'.CarbonImmutable::now()->subDays(120)->getTimestamp().'.M1P2.mail', 'body');
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(3)->getTimestamp().'.M3P4.mail', 'body');

    createCitoyen('jdoe', $this->home);
    createCitoyen('asmith', $stale);

    $this->artisan('citoyen:unread-mail', ['--min-days' => 30])
        ->expectsOutputToContain('asmith@marche.be')
        ->doesntExpectOutputToContain('jdoe@marche.be')
        ->assertSuccessful();

    File::deleteDirectory($stale);
});

it('lists citizens with their unread message count', function (): void {
    File::put($this->home.'/Maildir/new/1234567.mail', 'body');
    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail')
        ->expectsOutputToContain('jdoe@marche.be')
        ->expectsOutputToContain('1 compte(s), 1 message(s) non lu(s).')
        ->assertSuccessful();
});

it('reports accounts without a Maildir/new directory', function (): void {
    createCitoyen('jdoe', $this->home.'/nope');

    $this->artisan('citoyen:unread-mail')
        ->expectsOutputToContain('pas de Maildir/new')
        ->assertSuccessful();
});

it('hides accounts without unread messages when --only-unread is given', function (): void {
    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail', ['--only-unread' => true])
        ->expectsOutputToContain('Aucun compte correspondant.')
        ->assertSuccessful();
});

it('fails when no account matches the keyword', function (): void {

    $this->artisan('citoyen:unread-mail', ['keyword' => 'inconnu'])
        ->expectsOutputToContain('Aucun compte trouvé pour inconnu')
        ->assertFailed();
});

it('skips the accounts whose sieve script redirects, without proposing any deletion', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');
    writeSieveScript($this->home, 'redirect "perso@gmail.com";');
    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail', ['--delete' => true])
        ->expectsOutputToContain('Script(s) Sieve : actif.sieve')
        ->expectsOutputToContain('Redirection active vers : perso@gmail.com')
        ->expectsOutputToContain('Compte jdoe ignoré')
        ->doesntExpectOutputToContain('Supprimer le compte')
        ->expectsOutputToContain('0 compte(s) supprimé(s), 0 conservé(s), 1 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('proposes the deletion of an account whose sieve script does not redirect', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');
    writeSieveScript($this->home, 'require ["fileinto"]; fileinto "INBOX.Junk";');
    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail', ['--delete' => true])
        ->expectsOutputToContain('Script(s) Sieve : actif.sieve')
        ->expectsOutputToContain('Aucune redirection trouvée.')
        ->expectsConfirmation('Supprimer le compte jdoe ?', 'no')
        ->expectsOutputToContain('Suppression refusée')
        ->expectsOutputToContain('0 compte(s) supprimé(s), 1 conservé(s), 0 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('deletes an account without any sieve script, without asking for confirmation', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');

    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail', ['--delete' => true])
        ->expectsOutputToContain('Aucun script Sieve : aucune redirection possible, suppression directe.')
        ->doesntExpectOutputToContain('Supprimer le compte')
        ->expectsOutputToContain('Compte jdoe supprimé.')
        ->expectsOutputToContain('rm -rI '.$this->home)
        ->expectsOutputToContain('1 compte(s) supprimé(s), 0 conservé(s), 0 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('deletes the citizen and the mail account, and spares the others', function (): void {
    $citoyen = createCitoyen('jdoe', $this->home);
    $kept = createCitoyen('asmith', $this->home.'/does-not-exist');
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');

    $this->artisan('citoyen:unread-mail', ['--delete' => true, '--only-unread' => true])
        ->expectsOutputToContain('Compte jdoe supprimé.')
        ->assertSuccessful();

    expect(Citoyen::query()->where('uid', 'jdoe')->exists())->toBeFalse()
        ->and(VirtualUser::query()->where('username', $citoyen->mail)->exists())->toBeFalse()
        ->and(Citoyen::query()->whereKey($kept->getKey())->exists())->toBeTrue();
});

it('deletes the confirmed account whose sieve script does not redirect', function (): void {
    File::put($this->home.'/Maildir/new/'.CarbonImmutable::now()->subDays(60)->getTimestamp().'.M1P2.mail', 'body');
    writeSieveScript($this->home, 'require ["fileinto"]; fileinto "INBOX.Junk";');

    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail', ['--delete' => true])
        ->expectsOutputToContain('Aucune redirection trouvée.')
        ->expectsConfirmation('Supprimer le compte jdoe ?', 'yes')
        ->expectsOutputToContain('Compte jdoe supprimé.')
        ->expectsOutputToContain('1 compte(s) supprimé(s), 0 conservé(s), 0 ignoré(s) pour cause de redirection, 0 en erreur.')
        ->assertSuccessful();
});

it('does not propose any deletion without the --delete option', function (): void {
    File::put($this->home.'/Maildir/new/1234567.mail', 'body');
    createCitoyen('jdoe', $this->home);

    $this->artisan('citoyen:unread-mail')
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

    writeSieveScript($kept, 'require ["fileinto"]; fileinto "INBOX.Junk";');
    writeSieveScript($redirected, 'redirect "perso@gmail.com";');

    createCitoyen('jdoe', $this->home);
    createCitoyen('asmith', $kept);
    createCitoyen('bjones', $redirected);

    $this->artisan('citoyen:unread-mail', ['--delete' => true])
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
