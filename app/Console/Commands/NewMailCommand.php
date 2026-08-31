<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use App\Models\Citoyen;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Console\Command;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model;

use function Laravel\Prompts\confirm;

final class NewMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:new-mail
                            {keyword? : uid ou adresse mail à filtrer, tous les comptes si omis}
                            {--only-with-mail : N\'affiche que les comptes ayant au moins un message non lu}
                            {--min-days= : N\'affiche que les comptes dont le plus ancien message non lu dépasse ce nombre de jours}
                            {--delete : Propose la suppression LDAP et SQL des comptes listés, après vérification des redirections}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste les comptes citoyens ayant des messages non lus dans Maildir/new et depuis combien de temps';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keyword = $this->argument('keyword');
        $minDays = $this->option('min-days') !== null ? (int) $this->option('min-days') : null;
        $onlyWithMail = (bool) $this->option('only-with-mail') || $minDays !== null;

        try {
            $citizens = $keyword
                ? $this->ldapCitoyenRepository->search($keyword)
                : $this->ldapCitoyenRepository->getAll();
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (count($citizens) === 0) {
            $this->error('Aucun compte trouvé'.($keyword ? ' pour '.$keyword : ''));

            return self::FAILURE;
        }

        $now = CarbonImmutable::now();
        $rows = [];
        $totalNewMails = 0;

        foreach ($citizens as $citizen) {
            $homeDirectory = $citizen->getFirstAttribute('homeDirectory');
            $count = $this->ldapCitoyenRepository->countNewMails($homeDirectory);

            if ($onlyWithMail && ! $count) {
                continue;
            }

            $oldestAt = $this->ldapCitoyenRepository->oldestNewMailAt($homeDirectory);
            $days = $oldestAt ? (int) $oldestAt->diffInDays($now) : null;

            if ($minDays !== null && ($days === null || $days < $minDays)) {
                continue;
            }

            $totalNewMails += $count ?? 0;

            $rows[] = [
                'days' => $days,
                'citizen' => $citizen,
                'cells' => [
                    $citizen->getFirstAttribute('uid'),
                    $citizen->getFirstAttribute('mail'),
                    $count ?? 'pas de Maildir/new',
                    $oldestAt?->format('d/m/Y') ?? '—',
                    $days !== null ? $days.' j' : '—',
                ],
            ];
        }

        if (count($rows) === 0) {
            $this->line('Aucun compte correspondant.');

            return self::SUCCESS;
        }

        usort($rows, fn (array $a, array $b): int => ($b['days'] ?? -1) <=> ($a['days'] ?? -1));

        $this->table(
            ['uid', 'mail', 'non lus', 'plus ancien', 'en attente depuis'],
            array_column($rows, 'cells')
        );
        $this->info(count($rows).' compte(s), '.$totalNewMails.' message(s) non lu(s).');

        if ($this->option('delete')) {
            $this->deleteCitizens(array_column($rows, 'citizen'));
        }

        return self::SUCCESS;
    }

    /**
     * Supprime les comptes listés (LDAP puis SQL), après lecture de leur script Sieve.
     *
     * Sans script Sieve, aucune redirection n'est possible : le compte est
     * supprimé directement. Sinon, une redirection fait ignorer le compte —
     * il transfère son courrier ailleurs et reste donc utilisé malgré les
     * messages non lus — et l'absence de redirection déclenche une demande
     * de confirmation.
     *
     * @param  array<int, Model>  $citizens
     */
    private function deleteCitizens(array $citizens): void
    {
        /** @var array<int, array{0: string, 1: string, 2: string, 3: string}> $results */
        $results = [];

        foreach ($citizens as $citizen) {
            $uid = (string) $citizen->getFirstAttribute('uid');
            $mail = (string) $citizen->getFirstAttribute('mail');

            $this->newLine();
            $this->line(str_repeat('─', 60));
            $this->line("{$uid} ({$mail})");

            $sieveFiles = $this->ldapCitoyenRepository->findSieveFiles($uid);

            if ($sieveFiles === []) {
                $this->line('Aucun script Sieve : aucune redirection possible, suppression directe.');
            } else {
                $this->line('Script(s) Sieve : '.implode(', ', array_map(basename(...), $sieveFiles)));

                $redirects = $this->ldapCitoyenRepository->sieveRedirects($uid);

                if ($redirects !== []) {
                    $this->warn('Redirection active vers : '.implode(', ', $redirects));
                    $this->line('→ Compte '.$uid.' ignoré : son courrier est transféré, les messages non lus ne signifient pas qu\'il est abandonné.');
                    $results[] = [$uid, $mail, 'Ignoré', 'Redirection vers '.implode(', ', $redirects)];

                    continue;
                }

                $this->line('Aucune redirection trouvée.');
            }

            $confirmed = $sieveFiles === [] || confirm(
                label: "Supprimer le compte {$uid} ?",
                default: false,
            );

            if (! $confirmed) {
                $this->line("→ Compte {$uid} conservé.");
                $results[] = [$uid, $mail, 'Conservé', 'Suppression refusée'];

                continue;
            }

            try {
                $this->ldapCitoyenRepository->delete($uid);
                $this->info("✓ Compte {$uid} supprimé de l'annuaire LDAP.");

                $sqlDeleted = Citoyen::query()->where('uid', $uid)->delete();
                $this->line($sqlDeleted > 0
                    ? "  Entrée SQL supprimée pour {$uid}."
                    : "  Aucune entrée SQL trouvée pour {$uid}.");

                $this->line('  Pour supprimer le dossier imap : rm -rI '.$citizen->getFirstAttribute('homeDirectory'));
                $results[] = [$uid, $mail, 'Supprimé', 'rm -rI '.$citizen->getFirstAttribute('homeDirectory')];
            } catch (Exception|LdapRecordException $exception) {
                $this->error("Erreur lors de la suppression de {$uid} : {$exception->getMessage()}");
                $results[] = [$uid, $mail, 'Erreur', $exception->getMessage()];
            }
        }

        $this->displaySummary($results);
    }

    /**
     * Affiche le récapitulatif des comptes traités par l'option --delete.
     *
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $results
     */
    private function displaySummary(array $results): void
    {
        $this->newLine();

        if ($results === []) {
            $this->line('Aucun compte traité.');

            return;
        }

        $this->table(['uid', 'mail', 'statut', 'détail'], $results);

        $statuses = array_column($results, 2);
        $countOf = fn (string $status): int => count(array_keys($statuses, $status, true));

        $this->info(
            $countOf('Supprimé').' compte(s) supprimé(s), '.
            $countOf('Conservé').' conservé(s), '.
            $countOf('Ignoré').' ignoré(s) pour cause de redirection, '.
            $countOf('Erreur').' en erreur.'
        );
    }
}
