<?php

  return [
      'citoyen' => [
          'host' => env('LDAP_CITOYEN_URL'),
          'base_dn' => env('LDAP_CITOYEN_BASE'),
          'username' => env('LDAP_CITOYEN_ADMIN'),
          'password' => env('LDAP_CITOYEN_PWD'),
      ],
      'sieve_root' => env('SIEVE_ROOT'),
  ];
