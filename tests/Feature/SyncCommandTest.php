<?php

declare(strict_types=1);

use App\Models\Citoyen;
use Illuminate\Support\Facades\Schema;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

beforeEach(function (): void {
    Schema::create('login', function ($table): void {
        $table->id();
        $table->string('username');
        $table->dateTime('date_connect')->nullable();
        $table->string('protocol')->nullable();
        $table->integer('port')->nullable();
        $table->boolean('secure')->default(false);
    });
});

afterEach(function (): void {
    DirectoryFake::tearDown();
    Schema::dropIfExists('login');
});

/**
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

it('reports SQL citizens that no longer exist in the directory', function (): void {
    $directoryUids = collect(range(1, 201))->map(fn (int $i): string => 'user'.$i);
    $entries = $directoryUids->map(fn (string $uid): array => syncLdapEntryWithoutMail($uid))->all();

    makeSyncCitoyen('user1');
    makeSyncCitoyen('ghost');

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn($entries),
            LdapFake::operation('search')->andReturn($entries),
        ]);

    $this->artisan('citoyen:sync')
        ->doesntExpectOutputToContain('Removed from citoyenuser1')
        ->expectsOutputToContain('Removed from citoyenghost')
        ->assertSuccessful();

    expect(Citoyen::query()->count())->toBe(2);
});

it('syncs login data onto the matching citizen', function (): void {
    $citoyen = makeSyncCitoyen('jdoe');

    DB::table('login')->insert([
        'username' => 'jdoe',
        'date_connect' => '2026-08-30 10:00:00',
        'protocol' => 'imap',
        'port' => 993,
        'secure' => true,
    ]);

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect([
            LdapFake::operation('search')->andReturn([]),
            LdapFake::operation('search')->andReturn([]),
        ]);

    $this->artisan('citoyen:sync')
        ->expectsOutputToContain('Login data synced for 1 entries')
        ->assertSuccessful();

    $citoyen->refresh();

    expect($citoyen->protocol_connection)->toBe('imap')
        ->and($citoyen->port_connection)->toBe(993)
        ->and($citoyen->secure_connection)->toBeTrue()
        ->and($citoyen->last_connection->toDateString())->toBe('2026-08-30');
});
