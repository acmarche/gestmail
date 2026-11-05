<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Compte de messagerie lu par Dovecot : identifiant, mot de passe et quota.
 */
final class VirtualUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'userPassword',
        'legacy_password',
        'quota_bytes',
        'active',
    ];

    protected $hidden = [
        'userPassword',
        'legacy_password',
    ];

    /**
     * @return HasOne<Citoyen, $this>
     */
    public function citoyen(): HasOne
    {
        return $this->hasOne(Citoyen::class, 'mail', 'username');
    }

    protected function casts(): array
    {
        return [
            'quota_bytes' => 'integer',
            'active' => 'boolean',
        ];
    }
}
