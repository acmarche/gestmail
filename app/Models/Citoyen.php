<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class Citoyen extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'mail',
        'givenName',
        'sn',
        'l',
        'description',
        'postalAddress',
        'employeeNumber',
        'postalCode',
        'homeDirectory',
        'last_connection',
        'auth_token',
        'recovery_email',
        'recovery_phone',
        'charter_accepted_at',
    ];

    protected $hidden = [
        'auth_token',
        'remember_token',
        'hand_password',
    ];

    /**
     * @return BelongsTo<VirtualUser, $this>
     */
    public function virtualUser(): BelongsTo
    {
        return $this->belongsTo(VirtualUser::class, 'mail', 'username');
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
            'charter_accepted_at' => 'datetime',
        ];
    }
}
