<?php

namespace App\Repository;

use App\Models\Login;
use Illuminate\Database\Eloquent\Collection;

class LoginRepository
{
    /**
     * @return Collection<int, Login>
     */
    public function findAll(): Collection
    {
        return Login::all();
    }

    public function findByUsername(string $username): ?Login
    {
        return Login::query()->where('username', $username)->first();
    }

    public function delete(Login $login): void
    {
        $login->delete();
    }
}
