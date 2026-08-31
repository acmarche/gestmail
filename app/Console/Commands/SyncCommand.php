<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\CitoyenHandler;
use App\Ldap\CitoyenLdap;
use App\Ldap\LdapCitoyenRepository;
use App\Models\Citoyen;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Symfony\Component\Console\Command\Command as SfCommand;
use Throwable;

final class SyncCommand extends Command
{
    /**
     * L'annuaire doit dépasser ce nombre d'entrées pour que sa lecture soit
     * jugée complète : sinon ni purge SQL ni signalement d'orphelins, sous
     * peine de désigner à la suppression des comptes parfaitement valides.
     */
    private const MINIMUM_LDAP_ENTRIES = 200;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:sync
                            {--scan-imap : Analyse les répertoires IMAP : liste les manquants et totalise l\'espace occupé}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise les comptes citoyens de l\'annuaire LDAP vers la base SQL';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $scanImap = (bool) $this->option('scan-imap');

        /** @var array<int, string> $ldapUids */
        $ldapUids = [];

        foreach ($this->ldapCitoyenRepository->getAll() as $citoyenLdap) {
            $username = $citoyenLdap->getFirstAttribute('uid');
            $ldapUids[] = (string) $username;

            if (! $citoyenLdap->getFirstAttribute('mail')) {
                continue;
            }
            if (! $citoyen = Citoyen::where('uid', $username)->first()) {
                $citoyen = $this->addUser($citoyenLdap);
            } else {
                $this->updateUser($citoyen, $citoyenLdap);
            }

            if ($citoyen instanceof Citoyen) {
                $this->setLastLogin($citoyen);
            }
        }

        $this->missingFromLdap();

        if ($scanImap) {
            $this->reportOrphanMailboxes($ldapUids);
        }

        return SfCommand::SUCCESS;
    }

    private function addUser(CitoyenLdap $citoyenLdap): ?Citoyen
    {
        try {
            $citoyen = CitoyenHandler::createCitoyenDbFromLdap($citoyenLdap);
            $this->info('Added '.$citoyen->uid);

            return $citoyen;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return null;
        }
    }

    private function updateUser(Citoyen $citoyen, CitoyenLdap $citoyenLdap): void
    {
        $citoyen->update($citoyen->syncableDataFromLdap($citoyenLdap));
    }

    /**
     * Enregistre la date de la dernière relève du courrier, lue depuis le Maildir.
     */
    private function setLastLogin(Citoyen $citoyen): void
    {
        $lastLoginAt = $this->ldapCitoyenRepository->lastLoginAt($citoyen->homeDirectory);

        if (! $lastLoginAt) {
            return;
        }

        $citoyen->update(['last_connection' => $lastLoginAt]);
    }

    /**
     * Liste les répertoires IMAP qui ne correspondent plus à aucune entrée LDAP.
     *
     * @param  array<int, string>  $ldapUids
     */
    private function reportOrphanMailboxes(array $ldapUids): void
    {
        $this->newLine();

        if (count($ldapUids) <= self::MINIMUM_LDAP_ENTRIES) {
            $this->warn('Annuaire incomplet ('.count($ldapUids).' entrées) : analyse des répertoires IMAP abandonnée.');

            return;
        }

        $mailboxes = $this->ldapCitoyenRepository->allMailboxDirectories();

        if ($mailboxes === []) {
            $this->warn('Aucun répertoire IMAP trouvé sous '.$this->ldapCitoyenRepository->sieveRoot);

            return;
        }

        $known = array_flip($ldapUids);
        $rows = [];
        $totalBytes = 0;

        foreach ($mailboxes as $uid => $path) {
            if (isset($known[$uid])) {
                continue;
            }

            $bytes = $this->ldapCitoyenRepository->mailboxSize($path) ?? 0;
            $totalBytes += $bytes;
            $rows[] = ['uid' => $uid, 'path' => $path, 'bytes' => $bytes];
        }

        $this->info(count($mailboxes).' répertoire(s) IMAP examiné(s).');

        if ($rows === []) {
            $this->info('Aucun répertoire orphelin.');

            return;
        }

        usort($rows, fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

        $this->table(
            ['uid', 'répertoire', 'taille'],
            array_map(
                fn (array $row): array => [$row['uid'], $row['path'], Number::fileSize($row['bytes'], 2)],
                $rows
            )
        );

        $this->warn(
            count($rows).' répertoire(s) IMAP sans entrée LDAP, '
            .Number::fileSize($totalBytes, 2).' récupérable(s) (estimation Dovecot).'
        );
        $this->line('Aucune suppression effectuée : vérifiez la liste avant tout rm.');
    }

    private function missingFromLdap(): void
    {
        $ldapUsernames = [];

        foreach (CitoyenLdap::all() as $citoyenLdap) {
            $ldapUsernames[] = $citoyenLdap->getFirstAttribute('uid');
        }

        if (count($ldapUsernames) > self::MINIMUM_LDAP_ENTRIES) {
            foreach (Citoyen::all() as $citoyen) {
                if (! in_array($citoyen->uid, $ldapUsernames, true)) {
                    $citoyen->delete();
                    $this->info('Removed from citoyen '.$citoyen->uid);
                }
            }
        }
    }
}
