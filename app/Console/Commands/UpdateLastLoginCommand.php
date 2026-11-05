<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Citoyen;
use App\Repository\MailboxRepository;
use Illuminate\Console\Command;

final class UpdateLastLoginCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:update-last-login';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enregistre la dernière connexion de chaque citoyen, lue depuis son Maildir';

    public function __construct(private readonly MailboxRepository $mailboxRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = 0;

        Citoyen::query()->lazyById()->each(function (Citoyen $citoyen) use (&$updated): void {
            $lastLoginAt = $this->mailboxRepository->lastLoginAt($citoyen->homeDirectory);

            if (! $lastLoginAt) {
                return;
            }

            $citoyen->update(['last_connection' => $lastLoginAt]);
            $updated++;
        });

        $this->info($updated.' compte(s) mis à jour.');

        return self::SUCCESS;
    }
}
