<?php

declare(strict_types=1);

namespace App\Models;

use App\Ldap\CitoyenLdap;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class Citoyen extends Authenticatable implements FilamentUser, HasName
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
        'legacy_password',
        'gosaMailQuota',
        'gosaMailForwardingAddress',
        'gosaMailAlternateAddress',
        'last_connection',
        'protocol_connection',
        'port_connection',
        'secure_connection',
        'auth_token',
        'recovery_email',
        'recovery_phone',
        'charter_accepted_at',
        'password_changed_at',
    ];

    protected $hidden = [
        'auth_token',
        'remember_token',
        'legacy_password',
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
            'legacy_password' => $userLdap->getFirstAttribute('userPassword'),
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'citoyen';
    }

    public function getFilamentName(): string
    {
        return mb_trim($this->givenName.' '.$this->sn);
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->charter_accepted_at
            && $this->password_changed_at
            && ($this->recovery_email || $this->recovery_phone);
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mail);
    }

    protected function casts(): array
    {
        return [
            'last_connection' => 'date',
            'secure_connection' => 'boolean',
            'port_connection' => 'integer',
            'charter_accepted_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }
}
