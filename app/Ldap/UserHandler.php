<?php

namespace App\Ldap;

use App\Models\Citoyen;
use Exception;
use Illuminate\Support\Str;

final class UserHandler
{
    /**
     * @throws Exception
     */
    public static function createCitoyenDbFromLdap(CitoyenLdap $data): ?Citoyen
    {
        $username = $data['username'];
        if (Citoyen::where('username', $username)->first()) {
            throw new Exception('Utilisateur déjà existant');
        }
        $dataUser = Citoyen::generateDataFromLdap($data, $username);
        $dataUser['username'] = $username;
        $dataUser['password'] = Str::password();

        return Citoyen::create($dataUser);
    }
}
