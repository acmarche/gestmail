<?php

namespace App\Models;

use App\Ldap\CitoyenLdap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Citoyen extends Model
{
    use HasFactory;

    protected $fillable = [
        'givenName',
        'sn',
        'l',
        'email',
        'uid',
        'dn',
        'description',
        'postalAddress',
        'employeeNumber',
        'postalCode',
        'homeDirectory',
        'employeNumber',
        'gosaMailQuota',
        'gosaMailForwardingAddress',
        'gosaMailAlternateAddress',
    ];

    public static function generateDataFromLdap(CitoyenLdap $userLdap): array
    {
        return [
            'givenName' => $userLdap->getFirstAttribute('givenName'),
            'sn' => $userLdap->getFirstAttribute('sn'),
            'dn' => $userLdap->getDn(),
            'cn' => $userLdap->getFirstAttribute('cn'),
            'uid' => $userLdap->getFirstAttribute('uid'),
            'mail' => $userLdap->getFirstAttribute('mail'),
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
