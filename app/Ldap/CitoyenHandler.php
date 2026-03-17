<?php

declare(strict_types=1);

namespace App\Ldap;

use App\Models\Citoyen;
use App\Models\EmailDto;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ModelDoesNotExistException;

final class CitoyenHandler
{
    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository) {}

    /**
     * @throws Exception
     */
    public static function createCitoyenDbFromLdap(CitoyenLdap $data): ?Citoyen
    {
        if (Citoyen::where('uid', $data->getFirstAttribute('uid'))->first()) {
            throw new Exception('Utilisateur déjà existant');
        }
        $dataUser = Citoyen::generateDataFromLdap($data);
        $dataUser['userPassword'] = Str::password();

        return Citoyen::create($dataUser);
    }

    /**
     * @throws Exception
     * @throws LdapRecordException
     */
    public function createCitoyen(array $data): Citoyen
    {
        $emailDto = new EmailDto;
        $emailDto->givenName = $data['givenName'];
        $emailDto->sn = $data['sn'];
        $emailDto->mail = $data['mail'];
        $emailDto->postalAddress = $data['postalAddress'];
        $emailDto->postalCode = $data['postalCode'];
        $emailDto->l = $data['l'];
        $emailDto->employeeNumber = $data['employeeNumber'];
        $emailDto->userPassword = $data['password'];
        $emailDto->gosaMailQuota = (int) ($data['gosaMailQuota'] ?? 350);
        $emailDto->description = $data['description'] ?? null;

        $ldapEntry = $this->ldapCitoyenRepository->createCitizen($emailDto);

        return Citoyen::create(
            Citoyen::generateDataFromLdap($ldapEntry),
        );
    }

    /**
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     */
    public function updateCitoyen(Citoyen|Model $citoyen, CitoyenLdap $ldapEntry): void
    {
        $ldapEntry->setAttribute('givenName', $citoyen->givenName);
        $ldapEntry->setAttribute('sn', $citoyen->sn);
        $ldapEntry->setAttribute('cn', mb_trim($citoyen->givenName.' '.$citoyen->sn));
        $ldapEntry->setAttribute('description', $citoyen->description);
        $ldapEntry->setAttribute('employeeNumber', $citoyen->employeeNumber);
        $ldapEntry->setAttribute('postalAddress', $citoyen->postalAddress);
        $ldapEntry->setAttribute('postalCode', $citoyen->postalCode);
        $ldapEntry->setAttribute('l', $citoyen->l);

        $ldapEntry->update();
    }

    /**
     * @throws Exception
     */
    public function changePassword(Citoyen|Model $citoyen, string $password): void
    {
        $ldapEntry = $this->ldapCitoyenRepository->getEntry($citoyen->uid);

        if (! $ldapEntry) {
            throw new Exception('Utilisateur LDAP introuvable');
        }

        $this->ldapCitoyenRepository->changePassword($ldapEntry, $password);
    }

    /**
     * @throws Exception
     */
    public function changeQuota(Citoyen|Model $citoyen, int $quota): void
    {
        $ldapEntry = $this->ldapCitoyenRepository->getEntry($citoyen->uid);

        if (! $ldapEntry) {
            throw new Exception('Utilisateur LDAP introuvable');
        }

        $this->ldapCitoyenRepository->updateQuota($ldapEntry, $quota);
        $citoyen->update(['gosaMailQuota' => $quota]);
    }
}
