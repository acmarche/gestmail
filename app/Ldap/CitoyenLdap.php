<?php

namespace App\Ldap;

use LdapRecord\Models\Model;

class CitoyenLdap extends Model
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'gosaAccount',
        'gosaMailAccount',
        'top',
        'person',
        'organizationalPerson',
        'inetOrgPerson',
        'posixAccount',
    ];

    public static string $gosaMailDeliveryMode = '[CL]';
    public static string $gosaMailServer = 'imap://citoyen.marche.be';
    public static string $gosaSpamMailbox = 'INBOX\Junk';
    public static int $gosaSpamSortLevel = 0;

    public string $uid;
    public array $attributes = [];

    public static function convertDataToLdapSchema(
        string $uid,
        string $first_name,
        string $last_name,
        string $email,
        string $password,
        string $postalAddress,
        string $localite,
        string $postCode,
        string $homeDirectory,
        string $employeNumber,
        int $uidNumber,
        int $quota = 250
    ): array {
        return [
            'mail' => [$email],
            'gidNumber' => [5000],
            'uidNumber' => [$uidNumber],
            'givenName' => [$first_name],
            'employeeNumber' => [$employeNumber],
            'homeDirectory' => [$homeDirectory],
            'uid' => [$uid],
            'postalCode' => [$postCode],
            'postalAddress' => [$postalAddress],
            'userPassword' => [self::cryptPassword($password)],
            'l' => [$localite],
            'sn' => [$last_name],
            'cn' => [trim($first_name.' '.$last_name)],
            'objectClass' => self::$objectClasses,
            'gosaMailDeliveryMode' => [self::$gosaMailDeliveryMode],
            'gosaMailForwardingAddress' => [$uid.'@citoyen.marche.be'],
            'gosaMailServer' => [self::$gosaMailServer],
            'gosaSpamMailbox' => [self::$gosaSpamMailbox],
            'gosaSpamSortLevel' => [self::$gosaSpamSortLevel],
            'gosaMailQuota' => [$quota],
        ];
    }

    public static function cryptPassword(string $password): string
    {
        $salt = substr(sha1(uniqid(random_int(0, mt_getrandmax()), true), true), 0, 4);
        $rawHash = sha1($password.$salt, true).$salt;
        $method = '{SSHA}';

        return $method.base64_encode($rawHash);
    }
}
