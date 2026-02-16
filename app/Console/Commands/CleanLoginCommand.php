<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use App\Repository\LoginRepository;
use Illuminate\Console\Command;

class CleanLoginCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:login-clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoyage de la table login';

    public function __construct(
        private readonly LdapCitoyenRepository $ldapCitoyenRepository,
        private readonly LoginRepository $loginRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logins = $this->loginRepository->findAll();

        if ($logins->isEmpty()) {
            $this->info('Aucun enregistrement dans la table login.');

            return self::SUCCESS;
        }

        $this->info("Analyse de {$logins->count()} enregistrements...");

        $deletedCount = 0;

        foreach ($logins as $login) {
            try {
                $citizen = $this->ldapCitoyenRepository->getEntry($login->username);

                if (! $citizen) {
                    $this->loginRepository->delete($login);
                    $this->line("Supprimé : {$login->username}");
                    $deletedCount++;
                }
            } catch (\Exception $e) {
                $this->error("Erreur lors de la vérification de {$login->username} : {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info("Nettoyage terminé. {$deletedCount} enregistrement(s) supprimé(s).");

        return self::SUCCESS;
    }
}
