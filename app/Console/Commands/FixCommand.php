<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Exception;
use Illuminate\Console\Command;

final class FixCommand extends Command
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
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $this->line('Found '.count($citizens));

        $rows = [];
        foreach ($citizens as $citizen) {
            $alternates = $citizen->getAttribute('gosamailalternateaddress') ?? [];
            $rows[] = [
                $citizen->getFirstAttribute('mail'),
                implode(', ', $alternates),
            ];
        }

        $this->table(['Email', 'Alternate Addresses'], $rows);

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
