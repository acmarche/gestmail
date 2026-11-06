<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\DovecotPassword;
use Database\Factories\VirtualUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A mailbox login read by Dovecot, and the account citizens log in to their personal space with.
 *
 * The username is the full email address. The password hashes are hidden so they are never
 * serialized. Models are globally unguarded (see config/essentials.php), so leaving them out
 * of the fillable list does not stop them being mass assigned: never fill this from user input.
 */
#[Table('virtual_users')]
#[Fillable([
    'username',
    'quota_bytes',
    'active',
])]
#[Hidden([
    'userPassword',
    'legacy_password',
])]
#[UseFactory(VirtualUserFactory::class)]
final class VirtualUser extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory;

    /**
     * Same parameters as gestmail, which writes this column too. The memory cost is below
     * PHP's default because Dovecot verifies the hash on every login.
     *
     * @var array{memory_cost: int, time_cost: int, threads: int}
     */
    private const array ARGON2ID_OPTIONS = [
        'memory_cost' => 32768,
        'time_cost' => 4,
        'threads' => 1,
    ];

    /**
     * Stores the password in the format the Roundcube password plugin writes, which Dovecot
     * reads as its ARGON2ID scheme. Dovecot prefers userPassword over legacy_password, so
     * the legacy hash is cleared and can no longer be used.
     */
    public function changePassword(string $plainPassword): void
    {
        $this->forceFill([
            'userPassword' => '{ARGON2ID}'.password_hash($plainPassword, PASSWORD_ARGON2ID, self::ARGON2ID_OPTIONS),
            'legacy_password' => null,
        ])->save();
    }

    /**
     * @return HasOne<Citoyen, $this>
     */
    public function citoyen(): HasOne
    {
        return $this->hasOne(Citoyen::class, 'mail', 'username');
    }

    /**
     * Checks the hash Dovecot would use: userPassword when it is set, legacy_password otherwise.
     */
    public function checkPassword(string $plainPassword): bool
    {
        $hash = $this->userPassword ?? $this->legacy_password;

        return $hash !== null && DovecotPassword::check($plainPassword, $hash);
    }

    public function hasCompletedOnboarding(): bool
    {
        return (bool) $this->citoyen?->hasCompletedOnboarding();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'citoyen' && $this->active && $this->citoyen !== null;
    }

    public function getFilamentName(): string
    {
        return $this->citoyen?->getFullName() ?: $this->username;
    }

    /**
     * Left empty so that changing the password does not end the session of the citizen
     * who is changing it, see {@see \Illuminate\Session\Middleware\AuthenticateSession}.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * virtual_users, shared with Dovecot, has no remember token column.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (): string => $this->username);
    }

    protected function casts(): array
    {
        return [
            'quota_bytes' => 'integer',
            'active' => 'boolean',
        ];
    }
}
