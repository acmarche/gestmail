<?php

declare(strict_types=1);

namespace App\Ldap;

use App\Models\EmailDto;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\File;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelDoesNotExistException;
use LdapRecord\Query\Collection;
use SplFileInfo;

use function is_array;

final class LdapCitoyenRepository
{
    public string $sieveRoot = '/var/spool/dovecot/mail/';

    /**
     * @return Collection<CitoyenLdap>
     *
     * @throws Exception
     */
    public function getAll(): Collection
    {
        return CitoyenLdap::get();
    }

    /**
     * @throws Exception
     */
    public function getEntry(string $uid): ?Model
    {
        return CitoyenLdap::query()->findBy('uid', $uid);
    }

    /**
     * @throws Exception
     */
    public function getEntryByEmail(string $email): ?Model
    {
        return CitoyenLdap::query()->findBy('mail', $email);
    }

    /**
     * @return Collection|Model[]
     *
     * @throws Exception
     */
    public function checkExist(string $nom): Collection
    {
        return CitoyenLdap::query()
            ->orwhere('gosaMailAlternateAddress', '=', $nom)
            ->orWhere('mail', '=', $nom)
            ->orWhere('uid', '=', $nom)
            ->get();

        // $filter = "(&(|(mail=$nom)(gosaMailAlternateAddress=$nom)(gosaMailForwardingAddress=$nom)(uid=$nom))(objectClass=gosaMailAccount))";
    }

    /**
     * @return Collection|array|Model[]
     *
     * @throws Exception
     */
    public function search(string $nom): Collection|array
    {
        return CitoyenLdap::query()
            ->orWhere('uid', 'contains', $nom)
            ->orWhere('mail', 'contains', $nom)
            ->orWhere('gosaMailForwardingAddress', 'contains', $nom)
            ->get();
    }

    /**
     * Le premier uidNumber libre : un de plus que le plus haut présent dans
     * l'annuaire, 1 si celui-ci est vide.
     *
     * @throws Exception
     */
    public function getNextUidNumberCitoyen(): int
    {
        $highest = $this->getAll()
            ->map(fn ($entry) => (int) $entry->getFirstAttribute('uidNumber'))
            ->max();

        return (int) $highest + 1;
    }

    /**
     * @throws LdapRecordException
     * @throws Exception
     */
    public function createCitizen(EmailDto $emailCitoyen): CitoyenLdap
    {
        [$uid, $domain] = explode('@', $emailCitoyen->mail);
        $firstLetter = mb_substr($uid, 0, 1);

        $uidNumber = $this->getNextUidNumberCitoyen();
        $homeDirectory = $this->sieveRoot.$firstLetter.'/'.$uid;

        $data = CitoyenLdap::convertDataToLdapSchema(
            $uid,
            $emailCitoyen->givenName,
            $emailCitoyen->sn,
            $emailCitoyen->mail,
            $emailCitoyen->userPassword,
            $emailCitoyen->postalAddress,
            $emailCitoyen->l,
            $emailCitoyen->postalCode,
            $homeDirectory,
            $emailCitoyen->employeeNumber,
            $uidNumber,
            $emailCitoyen->gosaMailQuota,
        );
        $dn = 'uid='.$data['uid'][0].','.config('ldap.connections.citoyen.base_dn');

        $citoyenModel = new CitoyenLdap($data);
        $citoyenModel->setDn($dn);

        $citoyenModel->save();

        return $citoyenModel;
    }

    /**
     * @throws LdapRecordException
     * @throws Exception
     */
    public function update(Model $model, EmailDto $original, EmailDto $emailDto): void
    {
        $diff = array_diff_assoc((array) $emailDto, (array) $original);
        if (count($diff) > 0) {
            foreach ($diff as $key => $value) {
                $model->setAttribute($key, $value);
            }
            $model->save();
        }
    }

    /**
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     * @throws Exception
     */
    public function updateAlias(Model $model, iterable $alias): void
    {
        $model->setAttribute('gosaMailAlternateAddress', $alias);
        $model->update();
    }

    /**
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     * @throws Exception
     */
    public function updateQuota(Model $model, int $quota): void
    {
        $model->setAttribute('gosaMailQuota', $quota);
        $model->update();
    }

    /**
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     * @throws Exception
     */
    public function changePassword(Model $model, string $clearPassword): void
    {
        $model->setAttribute('userPassword', [CitoyenLdap::cryptPassword($clearPassword)]);
        $model->update();
    }

    /**
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     * @throws Exception
     */
    public function restorePassword(Model $model, string $cryptedPassword): void
    {
        $model->setAttribute('userPassword', [$cryptedPassword]);
        $model->update();
    }

    /**
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     * @throws Exception
     */
    public function delete(string $uid): void
    {
        $entry = $this->getEntry($uid);
        $entry->delete();
    }

    /**
     * Chemin du dossier contenant les scripts Sieve d'un citoyen.
     */
    public function sievePath(string $uid): string
    {
        return $this->sieveRoot.mb_substr($uid, 0, 1).'/'.$uid.'/sieve';
    }

    /**
     * Scripts Sieve présents pour un citoyen.
     *
     * @return array<int, string>
     */
    public function findSieveFiles(string $uid): array
    {
        $path = $this->sievePath($uid);

        if (! File::isDirectory($path)) {
            return [];
        }

        return File::glob($path.'/*.sieve');
    }

    /**
     * Adresses vers lesquelles les scripts Sieve du citoyen renvoient le courrier.
     *
     * Une redirection signifie que le compte transfère son courrier ailleurs :
     * les messages non lus ne prouvent alors pas que le compte est abandonné.
     *
     * @return array<int, string>
     */
    public function sieveRedirects(string $uid): array
    {
        $addresses = [];

        foreach ($this->findSieveFiles($uid) as $file) {
            $script = $this->stripSieveComments(File::get($file));

            if (preg_match_all('/\bredirect\b[^;"]*"([^"]+)"/i', $script, $matches) > 0) {
                $addresses = array_merge($addresses, $matches[1]);
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Chemin du dossier Maildir contenant les messages non lus d'un citoyen.
     */
    public function maildirNewPath(?string $homeDirectory): ?string
    {
        $maildir = $this->maildirPath($homeDirectory);

        return $maildir ? $maildir.'/new' : null;
    }

    /**
     * Nombre de messages non lus dans le Maildir d'un citoyen.
     * Retourne null si le dossier Maildir/new n'existe pas.
     */
    public function countNewMails(?string $homeDirectory): ?int
    {
        $path = $this->maildirNewPath($homeDirectory);

        if (! $path || ! File::isDirectory($path)) {
            return null;
        }

        return count(File::files($path));
    }

    /**
     * Date de dépôt du plus ancien message encore présent dans Maildir/new.
     *
     * Dovecot déplace les messages de new/ vers cur/ dès qu'un client IMAP
     * ouvre la INBOX : l'ancienneté de ce message indique donc depuis combien
     * de temps le citoyen n'a plus relevé son courrier.
     *
     * Retourne null si le dossier n'existe pas ou ne contient aucun message.
     */
    public function oldestNewMailAt(?string $homeDirectory): ?CarbonImmutable
    {
        $path = $this->maildirNewPath($homeDirectory);

        if (! $path || ! File::isDirectory($path)) {
            return null;
        }

        $timestamps = array_map(
            fn (SplFileInfo $file): int => $this->deliveryTimestamp($file),
            File::files($path)
        );

        if ($timestamps === []) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp(min($timestamps));
    }

    /**
     * Chemin du dossier Maildir d'un citoyen.
     */
    public function maildirPath(?string $homeDirectory): ?string
    {
        if (! $homeDirectory) {
            return null;
        }

        return mb_rtrim($homeDirectory, '/').'/Maildir';
    }

    /**
     * Date de la dernière relève du courrier par le citoyen.
     *
     * Dovecot réécrit son journal d'index à chaque session IMAP ou POP3 : la
     * date de modification de `dovecot.index.log` est donc la trace la plus
     * fiable de la dernière connexion. À défaut, le dossier `cur` est daté du
     * dernier déplacement d'un message lu depuis `new`, ce qui approche la
     * même information.
     *
     * Retourne null si aucune de ces traces n'existe.
     */
    public function lastLoginAt(?string $homeDirectory): ?CarbonImmutable
    {
        $maildir = $this->maildirPath($homeDirectory);

        if (! $maildir) {
            return null;
        }

        foreach ([$maildir.'/dovecot.index.log', $maildir.'/cur'] as $path) {
            if (File::exists($path)) {
                return CarbonImmutable::createFromTimestamp(File::lastModified($path));
            }
        }

        return null;
    }

    /**
     * @param  string  $mail
     * @return []
     */
    public function checkMailExist($mail, $list = false): array|bool
    {
        $check = [];
        $results = $this->checkExist($mail);
        $count = $results->count();

        if ($count > 0) {
            $result = $results[0];
            $check['dn'] = $result->getFirstAttribute('dn');
            $check['uid'] = $result->getFirstAttribute('uid');
            $check['src'] = 'citoyen';

            return $check;
        }

        /**
         * Staff.
         */
        $resultStaffs = $this->checkExist($mail);
        $countStaff = $resultStaffs->count();

        if ($countStaff > 0) {
            $result = $resultStaffs[0];
            $check['dn'] = $result->getFirstAttribute('dn');
            $check['src'] = 'staff';

            return $check;
        }

        /**
         * List
         * Lors creation on ne veut pas verifier si existe
         */
        if ($list) {
            $resultLists = $this->checkExist($mail);
            $countList = $resultLists->count();

            if ($countList > 0) {
                $result = $resultLists[0];
                $check['dn'] = $result->getFirstAttribute('dn');
                $check['src'] = 'liste';

                return $check;
            }
        }

        return false;
    }

    /**
     * Retourne tous les mail d'un tableau de entries.
     *
     * @param  Model[]  $entries
     * @param  bool  $getAlternates
     * @return []
     */
    public function getAllEmails(iterable $entries, $getAlternates = true, $server = 'mail'): array
    {
        $emails = [];
        foreach ($entries as $entry) {

            $mail = $entry->getFirstAttribute('mail');

            if ($mail && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $mail;
            }

            if ($getAlternates) {
                if ($server === 'mail') {
                    $alternates = $entry->getFirstAttribute('proxyAddresses', []);
                } else {
                    $alternates = $entry->getFirstAttribute('gosaMailAlternateAddress', []);
                }

                if (is_array($alternates)) {
                    $emails = array_merge($emails, $alternates);
                }
            }
        }

        sort($emails);

        return $emails;
    }

    /**
     * Retire les commentaires d'un script Sieve (# ligne et bloc slash-étoile).
     */
    private function stripSieveComments(string $script): string
    {
        $script = preg_replace('#/\*.*?\*/#s', '', $script) ?? $script;

        return preg_replace('/^\s*#.*$/m', '', $script) ?? $script;
    }

    /**
     * Date de dépôt d'un message Maildir.
     *
     * Le nom de fichier Maildir commence par l'horodatage unix de la remise
     * (ex: 1712345678.M123P456.host). On retombe sur la date de modification
     * du fichier si le nom ne suit pas cette convention.
     */
    private function deliveryTimestamp(SplFileInfo $file): int
    {
        if (preg_match('/^(\d{9,12})\./', $file->getFilename(), $matches) === 1) {
            return (int) $matches[1];
        }

        return $file->getMTime();
    }
}
