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
        $fixed = 0;
        foreach ($citizens as $citizen) {
            $mail = $citizen->getFirstAttribute('mail');
            $alternates = $citizen->getAttribute('gosamailalternateaddress') ?? [];

            if (count($alternates) === 1 && $alternates[0] === $mail) {
                $citizen->setAttribute('gosamailalternateaddress', []);
                $citizen->save();
                $fixed++;
                $alternates = ['(removed - same as mail)'];
            }

            if($mail == 'maud.demoitie@marche.be') {
               $citizen->setAttribute('gosamailalternateaddress', []);
                $citizen->save();
                $fixed++;
            }

            $rows[] = [
                $mail,
                implode(', ', $alternates),
            ];
        }

        $this->table(['Email', 'Alternate Addresses'], $rows);
        $this->info('Fixed '.$fixed.' citizens with duplicate alternate address.');

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
