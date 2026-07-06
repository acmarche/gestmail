<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\CitoyenHandler;
use App\Models\Citoyen;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\text;

final class PasswordCommand extends Command
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

    public function __construct(private readonly CitoyenHandler $citoyenHandler)
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

        $citoyen = Citoyen::where('mail', $mail)->first();

        if (! $citoyen) {
            $this->error('Citizen with email '.$mail.' not found');

            return self::FAILURE;
        }

        $newPassword = text(
            label: 'Nouveau mot de passe pour '.$citoyen->uid,
            required: true,
            validate: function (string $value): ?string {
                $validator = Validator::make(
                    ['password' => $value],
                    ['password' => Password::defaults()]
                );

                if ($validator->fails()) {
                    return 'Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule et un chiffre';
                }

                return null;
            }
        );

        try {
            $this->line('Try change password ');
            $this->citoyenHandler->changePasswordWithLdap($citoyen, $newPassword);
            $this->info('Password changed, try on https://citoyen.marche.be ');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
