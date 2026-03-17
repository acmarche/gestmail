<?php

declare(strict_types=1);

namespace App\Models;

use App\Ldap\CitoyenLdap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Citoyen extends Model
{
    use HasFactory;

    protected $fillable = [
        'givenName',
        'sn',
        'l',
        'mail',
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
        'last_connection',
        'protocol_connection',
        'port_connection',
        'secure_connection',
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
            'userPassword' => null,
            'employeeNumber' => $userLdap->getFirstAttribute('employeeNumber'),
            'gosaMailQuota' => $userLdap->getFirstAttribute('gosaMailQuota', 250),
            'gosaMailForwardingAddress' => $userLdap->getFirstAttribute('gosaMailForwardingAddress'),
            'gosaMailAlternateAddress' => $userLdap->getFirstAttribute('gosaMailAlternateAddress'),
            'homeDirectory' => $userLdap->getFirstAttribute('homeDirectory'),
            'description' => $userLdap->getFirstAttribute('description'),
        ];
    }

    protected function casts(): array
    {
        return [
            'last_connection' => 'date',
            'secure_connection' => 'boolean',
            'port_connection' => 'integer',
        ];
    }
}
