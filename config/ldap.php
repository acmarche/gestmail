<?php

  return [
    'hosts' => env('LDAP_DEFAULT_HOSTS', '127.0.0.1'),
    'base_dn' => env('LDAP_DEFAULT_BASE_DN', 'dc=local,dc=com'),
    'username' => env('LDAP_DEFAULT_USERNAME'),
    'password' => env('LDAP_DEFAULT_PASSWORD'),
    'port' => env('LDAP_DEFAULT_PORT', 389),
    'ssl' => env('LDAP_DEFAULT_SSL', false),
    'tls' => env('LDAP_DEFAULT_TLS', false),
    'sasl' => env('LDAP_DEFAULT_SASL', false),
    'timeout' => env('LDAP_DEFAULT_TIMEOUT', 5),
  ];
