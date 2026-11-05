<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Citoyen;
use App\Support\DovecotPassword;
use Illuminate\Support\Facades\DB;

final class CitoyenHandler
{
    /**
     * Supprime la fiche puis le compte de messagerie, dans cet ordre à cause de
     * la clef étrangère citoyens.mail. Le répertoire IMAP n'est pas touché.
     */
    public function deleteCitoyen(Citoyen $citoyen): void
    {
        DB::transaction(function () use ($citoyen): void {
            $virtualUser = $citoyen->virtualUser;

            $citoyen->delete();
            $virtualUser?->delete();
        });
    }

    /**
     * Enregistre le mot de passe en Argon2id dans virtual_users, là où Dovecot
     * le lit. Le legacy_password est effacé pour ne plus pouvoir servir.
     */
    public function changePassword(Citoyen $citoyen, string $password): void
    {
        $citoyen->virtualUser->update([
            'userPassword' => DovecotPassword::hash($password),
            'legacy_password' => null,
        ]);
    }
}
