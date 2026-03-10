<?php

declare(strict_types=1);

return [
    'connections' => [
        'citoyen' => [
            'hosts' => env('LDAP_CITOYEN_HOSTS', '127.0.0.1'),
            'base_dn' => env('LDAP_CITOYEN_BASE_DN', 'dc=local,dc=com'),
            'username' => env('LDAP_CITOYEN_USERNAME'),
            'password' => env('LDAP_CITOYEN_PASSWORD'),
            'port' => env('LDAP_CITOYEN_PORT', 389),
            'ssl' => env('LDAP_CITOYEN_SSL', false),
            'tls' => env('LDAP_CITOYEN_TLS', false),
            'sasl' => env('LDAP_CITOYEN_SASL', false),
            'timeout' => env('LDAP_CITOYEN_TIMEOUT', 5),
        ],
    ],
];
