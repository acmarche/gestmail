<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Citoyen;
use App\Repository\CitoyenRepository;
use App\Repository\MailboxRepository;
use App\Service\CitoyenHandler;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

final class UnreadMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:unread-mail
                            {keyword? : uid ou adresse mail à filtrer, tous les comptes si omis}
                            {--only-unread : N\'affiche que les comptes ayant au moins un message non lu}
                            {--min-days= : N\'affiche que les comptes dont le plus ancien message non lu dépasse ce nombre de jours}
                            {--delete : Propose la suppression des comptes listés, après vérification des redirections}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste les comptes citoyens ayant des messages non lus dans Maildir/new et depuis combien de temps';

    public function __construct(
        private readonly MailboxRepository $mailboxRepository,
        private readonly CitoyenRepository $citoyenRepository,
        private readonly CitoyenHandler $citoyenHandler,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keyword = $this->argument('keyword');
        $minDays = $this->option('min-days') !== null ? (int) $this->option('min-days') : null;
        $onlyUnread = (bool) $this->option('only-unread') || $minDays !== null;

        $citizens = $keyword
            ? $this->citoyenRepository->search($keyword)
            : Citoyen::all();

        if (count($citizens) === 0) {
            $this->error('Aucun compte trouvé'.($keyword ? ' pour '.$keyword : ''));

            return self::FAILURE;
        }

        $now = CarbonImmutable::now();
        $rows = [];
        $totalNewMails = 0;

        foreach ($citizens as $citizen) {
            $homeDirectory = $citizen->homeDirectory;
            $count = $this->mailboxRepository->countNewMails($homeDirectory);

            if ($onlyUnread && ! $count) {
                continue;
            }

            $oldestAt = $this->mailboxRepository->oldestNewMailAt($homeDirectory);
            $days = $oldestAt ? (int) $oldestAt->diffInDays($now) : null;

            if ($minDays !== null && ($days === null || $days < $minDays)) {
                continue;
            }

            $totalNewMails += $count ?? 0;

            $rows[] = [
                'days' => $days,
                'citizen' => $citizen,
                'cells' => [
                    $citizen->uid,
                    $citizen->mail,
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
     * Supprime les comptes listés, après lecture de leur script Sieve.
     *
     * Sans script Sieve, aucune redirection n'est possible : le compte est
     * supprimé directement. Sinon, une redirection fait ignorer le compte —
     * il transfère son courrier ailleurs et reste donc utilisé malgré les
     * messages non lus — et l'absence de redirection déclenche une demande
     * de confirmation.
     *
     * @param  array<int, Citoyen>  $citizens
     */
    private function deleteCitizens(array $citizens): void
    {
        /** @var array<int, array{0: string, 1: string, 2: string, 3: string}> $results */
        $results = [];

        foreach ($citizens as $citizen) {
            $uid = $citizen->uid;
            $mail = $citizen->mail;

            $this->newLine();
            $this->line(str_repeat('─', 60));
            $this->line("{$uid} ({$mail})");

            $sieveFiles = $this->mailboxRepository->findSieveFiles($citizen->homeDirectory);

            if ($sieveFiles === []) {
                $this->line('Aucun script Sieve : aucune redirection possible, suppression directe.');
            } else {
                $this->line('Script(s) Sieve : '.implode(', ', array_map(basename(...), $sieveFiles)));

                $redirects = $this->mailboxRepository->sieveRedirects($citizen->homeDirectory);

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
                $this->citoyenHandler->deleteCitoyen($citizen);
                $this->info("✓ Compte {$uid} supprimé.");

                $this->line('  Pour supprimer le dossier imap : rm -rI '.$citizen->homeDirectory);
                $results[] = [$uid, $mail, 'Supprimé', 'rm -rI '.$citizen->homeDirectory];
            } catch (Exception $exception) {
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
