<?php

namespace App\Repository;

use App\Models\Hand;

class HandRepository
{
    public function findByUid(string $uid): ?Hand
    {
        return Hand::query()->where('uid', $uid)->first();
    }

    public function create(string $uid, string $email, string $password): Hand
    {
        return Hand::create([
            'uid' => $uid,
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function delete(Hand $hand): void
    {
        $hand->delete();
    }
}
