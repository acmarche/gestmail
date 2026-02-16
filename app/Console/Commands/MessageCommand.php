<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use App\Mail\CitoyenMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

use function Laravel\Prompts\confirm;

class MessageCommand extends Command
{
    protected $signature = 'citoyen:send-message';

    protected $description = 'Envoie un message à tous les comptes citoyens';

    public function __construct(
        private readonly LdapCitoyenRepository $ldapCitoyenRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $citizens = $this->ldapCitoyenRepository->getAll();
        } catch (\Exception $e) {
            $this->error('Erreur lors de la récupération des citoyens: '.$e->getMessage());

            return self::FAILURE;
        }

        $emails = [];
        foreach ($citizens as $citizen) {
            $mail = $citizen->getFirstAttribute('mail');
            if ($mail && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $mail;
            }
        }

        $count = count($emails);

        if ($count === 0) {
            $this->warn('Aucun citoyen trouvé avec une adresse email valide.');

            return self::FAILURE;
        }

        $this->info("$count citoyens trouvés avec une adresse email valide.");
        $this->newLine();

        // Show preview
        $this->info('Aperçu du message (version texte):');
        $this->line('─────────────────────────────────────────');
        $this->line(view('mail.txt')->render());
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        if (! confirm("Voulez-vous envoyer ce message à $count citoyens ?", false)) {
            $this->info('Envoi annulé.');

            return self::SUCCESS;
        }

        $this->newLine();
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $sent = 0;
        $failed = 0;

        foreach ($emails as $email) {
            //$email = "jf@marche.be";
            try {
                Mail::to($email)->send(new CitoyenMessage);
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Erreur pour $email: ".$e->getMessage());
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Envoi terminé: $sent messages envoyés, $failed échecs.");

        return self::SUCCESS;
    }
}
