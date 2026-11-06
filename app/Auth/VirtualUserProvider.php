<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\VirtualUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Logs a mailbox in with its personal token, stored on the citoyen, or with its
 * username and password, read from the columns Dovecot uses.
 */
final class VirtualUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return VirtualUser::find($identifier);
    }

    /**
     * virtual_users, shared with Dovecot, has no remember token column.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $token = $credentials['token'] ?? null;

        if (is_string($token) && $token !== '') {
            return VirtualUser::whereRelation('citoyen', 'auth_token', $token)->first();
        }

        $username = $credentials['username'] ?? null;

        if (is_string($username) && $username !== '') {
            return VirtualUser::where('username', $username)->first();
        }

        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (! $user instanceof VirtualUser) {
            return false;
        }

        $token = $credentials['token'] ?? null;

        if (is_string($token) && $token !== '') {
            return hash_equals((string) $user->citoyen?->auth_token, $token);
        }

        $password = $credentials['password'] ?? null;

        return is_string($password) && $password !== '' && $user->checkPassword($password);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
}
