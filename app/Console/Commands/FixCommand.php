<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;

class FixCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recherche un compte citoyen suivant le mot clef';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $citizens = $this->ldapCitoyenRepository->getAll();
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $this->line('Found '.count($citizens));
        foreach ($citizens as $citizen) {
            if ($citizen->getFirstAttribute('gosamailalternateaddress')) {
                dump($citizen->getFirstAttribute('gosamailalternateaddress'));
            }
         //   $this->line($citizen->getFirstAttribute('mail'));
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
