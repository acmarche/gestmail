<?php

namespace App\Models;

class EmailDto
{
    public ?string $dn;

    public ?string $sn;

    public ?string $uid;

    public ?string $cn;

    public ?string $givenName;

    public ?string $postalAddress;

    public ?string $postalCode;

    public ?string $l;

    public ?string $mail;

    public ?string $userPassword;

    public ?string $employeeNumber;

    public int $gosaMailQuota;

    public ?string $gosaMailForwardingAddress;

    public ?string $gosaMailAlternateAddress;

    public ?string $uidNumber;

    public ?string $gidNumber;

    public ?string $description;

    public ?string $homeDirectory;

    public bool $force = false;
}
