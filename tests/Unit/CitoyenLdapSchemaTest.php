<?php

declare(strict_types=1);

use App\Ldap\CitoyenLdap;

function schemaFor(?string $firstName): array
{
    return CitoyenLdap::convertDataToLdapSchema(
        'jean.dupont',
        $firstName,
        'Dupont',
        'jean.dupont@marche.be',
        'SuperSecret123',
        'Rue de la Station 1',
        'Marche-en-Famenne',
        '6900',
        '/var/sieve/j/jean.dupont',
        '85073003328',
        1234,
    );
}

test('first name and last name are mapped to the right ldap attributes', function () {
    $data = schemaFor('Jean');

    expect($data['givenName'])->toBe(['Jean'])
        ->and($data['sn'])->toBe(['Dupont'])
        ->and($data['cn'])->toBe(['Jean Dupont']);
});

test('a missing first name is accepted and omits givenName', function () {
    $data = schemaFor(null);

    expect($data)->not->toHaveKey('givenName')
        ->and($data['sn'])->toBe(['Dupont'])
        ->and($data['cn'])->toBe(['Dupont']);
});
