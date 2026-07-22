<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Console\Command;

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
                            {--min-days= : N\'affiche que les comptes dont le plus ancien message non lu dépasse ce nombre de jours}';

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

        return self::SUCCESS;
    }
}
