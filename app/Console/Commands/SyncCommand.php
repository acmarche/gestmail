<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\CitoyenHandler;
use App\Ldap\CitoyenLdap;
use App\Ldap\LdapCitoyenRepository;
use App\Models\Citoyen;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SfCommand;
use Throwable;

final class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:sync';

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
        }

        $this->missingFromLdap();

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
