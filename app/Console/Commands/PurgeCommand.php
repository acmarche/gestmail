<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use DateTimeImmutable;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LdapRecord\LdapRecordException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class PurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoyage des adresses mails inactives';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoffDate = text(
            label: 'Date limite de connexion (les comptes non connectés depuis cette date seront proposés à la suppression)',
            placeholder: '2023-12-31',
            required: true,
            validate: function (string $value) {
                $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
                if (! $date || $date->format('Y-m-d') !== $value) {
                    return 'Le format de date doit être AAAA-MM-JJ (exemple: 2023-12-31)';
                }

                return null;
            },
            hint: 'Format: AAAA-MM-JJ (exemple: 2023-12-31)'
        );

        try {
            $citizens = $this->ldapCitoyenRepository->getAll();
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Analyse de {$citizens->count()} comptes LDAP...");
        $this->newLine();

        $processed = 0;
        $deleted = 0;
        $skipped = 0;

        foreach ($citizens as $citizen) {
            $uid = $citizen->getFirstAttribute('uid');
            $mail = $citizen->getFirstAttribute('mail');

            $login = DB::table('login')
                ->where('username', $uid)
                ->first();

            if (! $login) {
                continue;
            }

            if ($login->date_connect >= $cutoffDate) {
                continue;
            }

            $processed++;

            $this->line(str_repeat('─', 60));
            warning("Compte inactif trouvé : {$uid} ({$mail})");
            $this->line("Dernière connexion : {$login->date_connect}");

            $sieveFiles = $this->ldapCitoyenRepository->findSieveFiles($uid);

            if (count($sieveFiles) > 0) {
                $this->newLine();
                info('Script(s) Sieve trouvé(s) :');

                foreach ($sieveFiles as $sieveFile) {
                    $this->line("📄 {$sieveFile}");
                    $this->newLine();
                    $content = File::get($sieveFile);
                    $this->line($content);
                    $this->newLine();
                }
            } else {
                $this->line('Aucun script Sieve trouvé.');
            }

            $confirmed = confirm(
                label: "Supprimer le compte {$uid} ?",
                default: false
            );

            if ($confirmed) {
                try {
                    $this->ldapCitoyenRepository->delete($uid);
                    $homeDirectory = $citizen->getFirstAttribute('homeDirectory');
                    $this->info("✓ Compte {$uid} supprimé.");
                    $this->line("  Pour supprimer le dossier imap : rm -rI {$homeDirectory}");
                    $deleted++;
                } catch (Exception|LdapRecordException $e) {
                    $this->error("Erreur lors de la suppression : {$e->getMessage()}");
                }
            } else {
                $this->line("→ Compte {$uid} conservé.");
                $skipped++;
            }

            $this->newLine();
        }

        $this->line(str_repeat('═', 60));
        $this->info("Résumé : {$processed} comptes analysés, {$deleted} supprimés, {$skipped} conservés.");

        return self::SUCCESS;
    }
}
