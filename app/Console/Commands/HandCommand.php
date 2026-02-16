<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use App\Repository\HandRepository;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class HandCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:hand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prendre la main sur un compte citoyen';

    public function __construct(
        private readonly LdapCitoyenRepository $ldapCitoyenRepository,
        private readonly HandRepository $handRepository
    ) {
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

            return self::FAILURE;
        }

        if (! $entry) {
            $this->error('Citizen with email '.$mail.' not found');

            return self::FAILURE;
        }

        $uid = $entry->getFirstAttribute('uid');
        $originalPassword = $entry->getFirstAttribute('userPassword');

        // Check if already taken over
        $existingHand = $this->handRepository->findByUid($uid);
        if ($existingHand) {
            $this->warn("Un takeover existe déjà pour $uid");

            if (confirm('Voulez-vous restaurer le mot de passe original ?')) {
                try {
                    $this->ldapCitoyenRepository->restorePassword($entry, $existingHand->password);
                    $this->handRepository->delete($existingHand);
                    $this->info('Mot de passe original restauré avec succès.');
                } catch (\Exception $e) {
                    $this->error('Erreur lors de la restauration: '.$e->getMessage());

                    return self::FAILURE;
                }
            }

            return self::SUCCESS;
        }

        // Store original password
        $this->handRepository->create($uid, $mail, $originalPassword);
        $this->info('Mot de passe original sauvegardé.');

        // Ask for temporary password
        $tempPassword = password(
            label: 'Nouveau mot de passe temporaire',
            required: true,
        );

        try {
            $this->ldapCitoyenRepository->changePassword($entry, $tempPassword);
            $this->info('Mot de passe temporaire défini avec succès.');
        } catch (\Exception $e) {
            $this->error('Erreur lors du changement de mot de passe: '.$e->getMessage());

            return self::FAILURE;
        }

        // Wait for admin to finish
        $this->newLine();
        $this->info('Vous pouvez maintenant vous connecter avec le mot de passe temporaire.');
        $this->info('Exécutez à nouveau cette commande avec le même email pour restaurer le mot de passe original.');

        return self::SUCCESS;
    }
}
