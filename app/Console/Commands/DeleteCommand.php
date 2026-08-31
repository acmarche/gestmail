<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use App\Models\Citoyen;
use Exception;
use Illuminate\Console\Command;
use LdapRecord\LdapRecordException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

final class DeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suppression d\'un compte citoyen (annuaire LDAP et base SQL)';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $uid = text(
            label: 'Nom d\'utilisateur',
            required: true,
        );

        try {
            $citizen = $this->ldapCitoyenRepository->getEntry($uid);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $citizen) {
            $this->error('Citizen with uid '.$uid.' not found');

            return self::FAILURE;
        }

        $email = $citizen->getFirstAttribute('mail');
        if (! $email) {
            $this->warn("Pas d'adresse mail trouvée pour {$uid}");
        } else {
            $this->info("Adresse mail trouvée : {$uid} ({$email})");
        }

        $confirmed = confirm(
            label: "Êtes-vous sûr de vouloir supprimer le compte de {$uid} ?",
            default: false
        );

        if (! $confirmed) {
            $this->info('Suppression annulée.');

            return self::SUCCESS;
        }

        try {
            $this->ldapCitoyenRepository->delete($uid);
            $this->info("Le compte {$uid} a été supprimé de l'annuaire LDAP.");

            $sqlDeleted = Citoyen::query()->where('uid', $uid)->delete();
            $this->info($sqlDeleted > 0
                ? "L'entrée SQL de {$uid} a été supprimée."
                : "Aucune entrée SQL trouvée pour {$uid}.");

            $attribute = $citizen->getAttribute('homeDirectory');
            $chemin = (string) $attribute[0];
            $this->info("Le dossier imap peut être supprimé avec la commande rm -rI {$chemin}.");

        } catch (Exception|LdapRecordException $exception) {
            $error = $exception->getMessage();
            if ($exception instanceof LdapRecordException) {
                $error .= ' '.$exception->getDetailedError()->getDiagnosticMessage();
            }
            $this->error("L'erreur suivante est survenue : $error");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
