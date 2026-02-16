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
        if (Citoyen::where('uid', $data->getFirstAttribute('uid'))->first()) {
            throw new Exception('Utilisateur déjà existant');
        }
        $dataUser = Citoyen::generateDataFromLdap($data);
        $dataUser['password'] = Str::password();

        return Citoyen::create($dataUser);
    }
}
