<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Citoyen;
use App\Repository\MailboxRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;

final class OrphanMailboxesCommand extends Command
{
    /**
     * La table citoyens doit dépasser ce nombre de comptes pour que sa lecture
     * soit jugée complète : sinon aucun signalement d'orphelins, sous peine de
     * désigner à la suppression des boîtes parfaitement valides.
     */
    private const MINIMUM_CITOYENS = 200;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:orphan-mailboxes
                            {--delete : Supprime les répertoires orphelins listés, sans confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste les répertoires IMAP sans compte dans la table citoyens et l\'espace qu\'ils occupent';

    public function __construct(private readonly MailboxRepository $mailboxRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * Les noms sont comparés à ceux des homeDirectory, sans tenir compte de la
     * casse : une boîte référencée par un compte n'est jamais orpheline.
     */
    public function handle(): int
    {
        $homeDirectories = Citoyen::query()->pluck('homeDirectory');

        if ($homeDirectories->count() <= self::MINIMUM_CITOYENS) {
            $this->warn('Table citoyens incomplète ('.$homeDirectories->count().' comptes) : analyse des répertoires IMAP abandonnée.');

            return self::SUCCESS;
        }

        $mailboxes = $this->mailboxRepository->allMailboxDirectories();

        if ($mailboxes === []) {
            $this->warn('Aucun répertoire IMAP trouvé sous '.$this->mailboxRepository->mailRoot);

            return self::SUCCESS;
        }

        $known = $homeDirectories
            ->mapWithKeys(fn (string $path): array => [mb_strtolower(basename($path)) => true])
            ->all();
        $rows = [];
        $totalBytes = 0;

        foreach ($mailboxes as $name => $path) {
            if (isset($known[mb_strtolower((string) $name)])) {
                continue;
            }

            $bytes = $this->mailboxRepository->mailboxSize($path) ?? 0;
            $totalBytes += $bytes;
            $rows[] = ['name' => (string) $name, 'path' => $path, 'bytes' => $bytes];
        }

        $this->info(count($mailboxes).' répertoire(s) IMAP examiné(s).');

        if ($rows === []) {
            $this->info('Aucun répertoire orphelin.');

            return self::SUCCESS;
        }

        usort($rows, fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

        $this->table(
            ['compte', 'répertoire', 'taille'],
            array_map(
                fn (array $row): array => [$row['name'], $row['path'], Number::fileSize($row['bytes'], 2)],
                $rows
            )
        );

        $this->warn(
            count($rows).' répertoire(s) IMAP sans compte citoyen, '
            .Number::fileSize($totalBytes, 2).' récupérable(s) (estimation Dovecot).'
        );

        if (! $this->option('delete')) {
            $this->line('Aucune suppression effectuée : vérifiez la liste avant de relancer avec --delete.');

            return self::SUCCESS;
        }

        $this->deleteOrphanMailboxes($rows);

        return self::SUCCESS;
    }

    /**
     * Supprime définitivement les répertoires orphelins listés.
     *
     * Appelée uniquement derrière le garde-fou de self::MINIMUM_CITOYENS : sur
     * une table partielle, ce sont des boîtes valides qui seraient effacées.
     *
     * @param  array<int, array{name: string, path: string, bytes: int}>  $orphans
     */
    private function deleteOrphanMailboxes(array $orphans): void
    {
        $this->newLine();

        $deleted = 0;
        $freedBytes = 0;
        /** @var array<int, string> $failed */
        $failed = [];

        foreach ($orphans as $orphan) {
            if (File::deleteDirectory($orphan['path'])) {
                $deleted++;
                $freedBytes += $orphan['bytes'];
                $this->line('Supprimé : '.$orphan['path']);

                continue;
            }

            $failed[] = $orphan['name'];
            $this->error('Échec de la suppression de '.$orphan['path']);
        }

        $this->info($deleted.' répertoire(s) supprimé(s), '.Number::fileSize($freedBytes, 2).' libéré(s).');

        if ($failed !== []) {
            $this->error(count($failed).' échec(s) : '.implode(', ', $failed));
        }
    }
}
