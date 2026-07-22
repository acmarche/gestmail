<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
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
                            {--only-with-mail : N\'affiche que les comptes ayant au moins un message non lu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste les comptes citoyens ayant des messages non lus dans Maildir/new';

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

        $rows = [];
        $totalNewMails = 0;

        foreach ($citizens as $citizen) {
            $homeDirectory = $citizen->getFirstAttribute('homeDirectory');
            $count = $this->ldapCitoyenRepository->countNewMails($homeDirectory);

            if ($this->option('only-with-mail') && ! $count) {
                continue;
            }

            $totalNewMails += $count ?? 0;

            $rows[] = [
                $citizen->getFirstAttribute('uid'),
                $citizen->getFirstAttribute('mail'),
                $count ?? 'pas de Maildir/new',
                $this->ldapCitoyenRepository->maildirNewPath($homeDirectory) ?? '—',
            ];
        }

        if (count($rows) === 0) {
            $this->line('Aucun compte avec des messages non lus.');

            return self::SUCCESS;
        }

        $this->table(['uid', 'mail', 'non lus', 'Maildir'], $rows);
        $this->info(count($rows).' compte(s), '.$totalNewMails.' message(s) non lu(s).');

        return self::SUCCESS;
    }
}
