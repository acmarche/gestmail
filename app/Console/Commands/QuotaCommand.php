<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ModelDoesNotExistException;

use function Laravel\Prompts\text;

class QuotaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:quota';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change le quota du compte citoyen';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mail = text(
            label: 'Pour quelle adresse email',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : "L'adresse mail n'a pas un format valide"
        );

        try {
            $entry = $this->ldapCitoyenRepository->getEntryByEmail($mail);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }
        if (! $entry) {
            $this->error('Citizen with email '.$mail.' not found');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $quota = text(
            label: 'Nouveau quota (en MB, entre 250 et 5000)',
            placeholder: '250',
            required: true,
            validate: function (string $value) {
                if (! ctype_digit($value)) {
                    return 'Le quota doit être un nombre entier';
                }

                $intValue = (int) $value;
                if ($intValue < 250 || $intValue > 5000) {
                    return 'Le quota doit être compris entre 250 MB et 5000 MB (5 Go)';
                }

                return null;
            }
        );

        try {
            $quotaInKb = (int) $quota * 1024;
            $this->ldapCitoyenRepository->updateQuota($entry, $quotaInKb);
            $this->info("Quota changed to {$quota} MB ({$quotaInKb} KB)");

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        } catch (\Exception|ModelDoesNotExistException|LdapRecordException $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

    }
}
