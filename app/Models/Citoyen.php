<?php

namespace App\Models;

use App\Ldap\CitoyenLdap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Citoyen extends Model
{
    /** @use HasFactory<\Database\Factories\CitoyenFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'givenName',
        'sn',
        'l',
        'email',
        'uuid',
        'postalAddress',
        'employeeNumber',
        'postalCode',
        'homeDirectory',
        'employeNumber',
        'gosaMailQuota',
        'gosaMailForwardingAddress',
        'gosaMailAlternateAddress',
    ];

    public static function generateDataFromLdap(CitoyenLdap $userLdap, string $username): array
    {
        $email = $userLdap->getFirstAttribute('mail');

        return [
            'givenname' => $userLdap->getFirstAttribute('givenname'),
            'sn' => $userLdap->getFirstAttribute('sn'),
            'email' => $email,
            'uuid' => self::getUuidFromIntranetDb($username),
            'dn' => $userLdap->getDn(),
            'cn' => $userLdap->getFirstAttribute('cn'),
            'uid' => $userLdap->getFirstAttribute('uid'),
            'mail' => $userLdap->getFirstAttribute('mail'),
            'givenName' => $userLdap->getFirstAttribute('givenName'),
            'postalAddress' => $userLdap->getFirstAttribute('postalAddress'),
            'postalCode' => $userLdap->getFirstAttribute('postalCode'),
            'l' => $userLdap->getFirstAttribute('l'),
            'userPassword' => $userLdap->getFirstAttribute('userPassword'),
            'employeeNumber' => $userLdap->getFirstAttribute('employeeNumber'),
            'gosaMailQuota' => $userLdap->getFirstAttribute('gosaMailQuota', 250),
            'gosaMailForwardingAddress' => $userLdap->getFirstAttribute('gosaMailForwardingAddress'),
            'gosaMailAlternateAddress' => $userLdap->getFirstAttribute('gosaMailAlternateAddress'),
            'homeDirectory' => $userLdap->getFirstAttribute('homeDirectory'),
            'description' => $userLdap->getFirstAttribute('description'),
        ];
    }
}
