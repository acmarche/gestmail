<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Imap\ImapCitoyen;
use App\Ldap\CitoyenLdap;
use DateTimeInterface;
use Exception;
use Illuminate\Console\Command;

final class SearchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:search {keyword}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recherche un compte citoyen suivant le mot clef';

    public function __construct(
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $uid = $this->argument('keyword') ?? null;

        if ($uid) {
            try {
                $citizens = CitoyenLdap::query()->where($uid, 'contains')->get();
            } catch (Exception $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if (count($citizens) === 0) {
                $this->line('not found '.$uid);

                return self::FAILURE;
            }

            $this->line('Found '.count($citizens));

            $imapCitoyen = new ImapCitoyen(
                config('imap.citoyen.host'),
                config('imap.citoyen.user'),
                config('imap.citoyen.password')
            );

            foreach ($citizens as $citizen) {
                $username = $citizen->getFirstAttribute('uid');
                $mail = $citizen->getFirstAttribute('mail');
                $quota = $citizen->getFirstAttribute('gosaMailQuota');

                $quotaDisplay = $quota ? "quota: $quota Mo" : 'quota: non défini';

                try {
                    $quotaInfo = $imapCitoyen->getQuota($username);
                    $usageMo = round($quotaInfo['usage'] / 1024, 2);
                    $quotaDisplay = "usage: $usageMo Mo / $quota Mo ({$quotaInfo['pourcentage']}%)";
                } catch (Exception $e) {
                    $this->error('Can\'t get quota for '.$username.'.'.$e->getMessage());
                }

                if ($citizen->last_connection instanceof DateTimeInterface) {
                    $this->line("$mail ($quotaDisplay, dernière connexion : {$citizen->last_connection->format('d/m/Y')})");
                } else {
                    $this->line("$mail ($quotaDisplay, pas de dernière connexion trouvée)");
                }
            }
        }

        return self::SUCCESS;
    }
}
