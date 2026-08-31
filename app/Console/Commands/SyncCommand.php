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

        /** @var array<int, string> $missingMailboxes */
        $missingMailboxes = [];
        /** @var array<int, string> $unmeasuredMailboxes */
        $unmeasuredMailboxes = [];
        $scannedMailboxes = 0;
        $totalBytes = 0;

        foreach ($this->ldapCitoyenRepository->getAll() as $citoyenLdap) {
            if (! $citoyenLdap->getFirstAttribute('mail')) {
                continue;
            }
            $username = $citoyenLdap->getFirstAttribute('uid');
            if (! $citoyen = Citoyen::where('uid', $username)->first()) {
                $citoyen = $this->addUser($citoyenLdap);
            } else {
                $this->updateUser($citoyen, $citoyenLdap);
            }

            if ($citoyen instanceof Citoyen) {
                $this->setLastLogin($citoyen);
            }

            if ($scanImap) {
                $homeDirectory = $citoyenLdap->getFirstAttribute('homeDirectory');
                $bytes = $this->ldapCitoyenRepository->hasMailbox($homeDirectory)
                    ? $this->ldapCitoyenRepository->mailboxSize($homeDirectory)
                    : false;

                if ($bytes === false) {
                    $missingMailboxes[] = (string) $username;
                } elseif ($bytes === null) {
                    $unmeasuredMailboxes[] = (string) $username;
                } else {
                    $scannedMailboxes++;
                    $totalBytes += $bytes;
                }
            }
        }

        $this->missingFromLdap();

        if ($scanImap) {
            $this->reportImapScan($missingMailboxes, $unmeasuredMailboxes, $scannedMailboxes, $totalBytes);
        }

        return SfCommand::SUCCESS;
    }

    /**
     * Affiche le résultat de l'analyse des répertoires IMAP.
     *
     * @param  array<int, string>  $missingMailboxes
     * @param  array<int, string>  $unmeasuredMailboxes
     */
    private function reportImapScan(
        array $missingMailboxes,
        array $unmeasuredMailboxes,
        int $scannedMailboxes,
        int $totalBytes,
    ): void {
        $this->newLine();

        $this->listMailboxes($missingMailboxes, 'répertoire(s) IMAP introuvable(s)');
        $this->listMailboxes($unmeasuredMailboxes, 'répertoire(s) IMAP sans fichier maildirsize');

        $this->info(
            $scannedMailboxes.' répertoire(s) IMAP analysé(s), espace occupé : '
            .Number::fileSize($totalBytes, 2).' (estimation Dovecot)'
        );
    }

    /**
     * @param  array<int, string>  $usernames
     */
    private function listMailboxes(array $usernames, string $label): void
    {
        if ($usernames === []) {
            return;
        }

        $this->warn(count($usernames).' '.$label.' :');

        foreach ($usernames as $username) {
            $this->line('  - '.$username);
        }

        $this->newLine();
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

    private function missingFromLdap(): void
    {
        $ldapUsernames = [];

        foreach (CitoyenLdap::all() as $citoyenLdap) {
            $ldapUsernames[] = $citoyenLdap->getFirstAttribute('uid');
        }

        if (count($ldapUsernames) > 200) {
            foreach (Citoyen::all() as $citoyen) {
                if (! in_array($citoyen->uid, $ldapUsernames, true)) {
                    $citoyen->delete();
                    $this->info('Removed from citoyen '.$citoyen->uid);
                }
            }
        }
    }
}
