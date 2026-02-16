<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ModelDoesNotExistException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class PasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:password';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change le mot de passe du compte citoyen';

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
            validate: fn(string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : "L'adresse mail n'a pas un format valide"
        );

        try {
            $entry = $this->ldapCitoyenRepository->getEntryByEmail($mail);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }
        if (!$entry) {
            $this->error('Citizen with email '.$mail.' not found');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $newPassword = text(
            label: 'Nouveau mot de passe pour '.$entry->getFirstAttribute('uid'),
            required: true,
            validate: function (string $value) {
                $validator = Validator::make(
                    ['password' => $value],
                    ['password' => Password::min(8)->letters()->mixedCase()->numbers()]
                );

                if ($validator->fails()) {
                    return 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre';
                }

                return null;
            }
        );

        try {
            $this->line('Try change password ');
            $this->ldapCitoyenRepository->changePassword($entry, $newPassword);
            $this->info('Password changed, try on https://citoyen.marche.be ');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        } catch (\Exception|ModelDoesNotExistException|LdapRecordException $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

    }
}
