<?php

declare(strict_types=1);

use App\Models\Citoyen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->home = sys_get_temp_dir().'/gestmail-login-'.uniqid();
    File::makeDirectory($this->home.'/Maildir', 0755, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->home);
});

function makeLoginCitoyen(string $mail, string $homeDirectory): Citoyen
{
    return Citoyen::factory()->create([
        'uid' => Str::before($mail, '@'),
        'homeDirectory' => $homeDirectory,
    ]);
}

it('stores the last connection date of the citizen', function (): void {
    $connectedAt = CarbonImmutable::parse('2026-05-20 12:00:00');
    File::put($this->home.'/Maildir/dovecot.index.log', 'binary');
    touch($this->home.'/Maildir/dovecot.index.log', $connectedAt->getTimestamp());

    $citoyen = makeLoginCitoyen('jdoe@marche.be', $this->home);

    $this->artisan('citoyen:update-last-login')->assertSuccessful();

    expect($citoyen->refresh()->last_connection->toDateString())->toBe('2026-05-20');
});

it('reports the number of updated accounts', function (): void {
    makeLoginCitoyen('withmail@marche.be', $this->home.'/does-not-exist');

    $this->artisan('citoyen:update-last-login')
        ->expectsOutputToContain('0 compte(s) mis à jour.')
        ->assertSuccessful();
});

it('keeps the last connection when the Maildir holds no trace', function (): void {
    $citoyen = makeLoginCitoyen('jdoe@marche.be', $this->home.'/does-not-exist');
    $citoyen->update(['last_connection' => '2025-01-15']);

    $this->artisan('citoyen:update-last-login')->assertSuccessful();

    expect($citoyen->refresh()->last_connection->toDateString())->toBe('2025-01-15');
});
