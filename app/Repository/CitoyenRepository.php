<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Citoyen;
use Illuminate\Database\Eloquent\Collection;

final class CitoyenRepository
{
    /**
     * @return Collection<int, Citoyen>
     */
    public function search(string $keyword): Collection
    {
        return Citoyen::query()
            ->where('uid', 'like', "%{$keyword}%")
            ->orWhere('mail', 'like', "%{$keyword}%")
            ->get();
    }
}
