<?php

  return [
      'citoyen' => [
          'host' => env('LDAP_CITOYEN_URL'),
          'user' => env('LDAP_CITOYEN_ADMIN'),
          'password' => env('LDAP_CITOYEN_PWD'),
      ],
  ];
