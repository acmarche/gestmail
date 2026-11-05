<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Citoyen;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

final class CitoyenTokenProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return Citoyen::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return Citoyen::where('id', $identifier)
            ->where('remember_token', $token)
            ->first();
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $user->remember_token = $token;
        $user->save();
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! isset($credentials['token'])) {
            return null;
        }

        return Citoyen::where('auth_token', $credentials['token'])->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return isset($credentials['token']) && $user->auth_token === $credentials['token'];
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // No-op: token auth has no password to rehash
    }
}
