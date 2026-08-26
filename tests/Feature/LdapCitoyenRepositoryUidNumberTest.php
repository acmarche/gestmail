<?php

declare(strict_types=1);

use App\Ldap\LdapCitoyenRepository;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

afterEach(function (): void {
    DirectoryFake::tearDown();
});

/**
 * @param  array<int, int>  $uidNumbers
 */
function fakeDirectoryWithUidNumbers(array $uidNumbers): void
{
    $entries = array_map(fn (int $uidNumber): array => [
        'dn' => ["uid=user{$uidNumber},ou=Users,ou=Citoyens,dc=marche,dc=be"],
        'uid' => ["user{$uidNumber}"],
        'uidNumber' => [(string) $uidNumber],
    ], $uidNumbers);

    DirectoryFake::setup('citoyen')
        ->getLdapConnection()
        ->expect(LdapFake::operation('search')->andReturn($entries));
}

it('gives the next uidNumber above the highest one in the directory', function (): void {
    fakeDirectoryWithUidNumbers([5001, 5010, 5004]);

    expect(app(LdapCitoyenRepository::class)->getNextUidNumberCitoyen())->toBe(5011);
});

it('starts at 1 when the directory holds no citizen', function (): void {
    fakeDirectoryWithUidNumbers([]);

    expect(app(LdapCitoyenRepository::class)->getNextUidNumberCitoyen())->toBe(1);
});
