<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;

class SearchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:search {keyword}';

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
        $uid = $this->argument('keyword') ?? null;

        if ($uid) {
            try {
                $citizens = $this->ldapCitoyenRepository->search($uid);
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return \Symfony\Component\Console\Command\Command::FAILURE;
            }

            if (count($citizens) === 0) {
                $this->line('not found '.$uid);

                return \Symfony\Component\Console\Command\Command::FAILURE;
            }

            $this->line('Found '.count($citizens));
            foreach ($citizens as $citizen) {
                $this->line($citizen->getFirstAttribute('mail'));
            }
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
