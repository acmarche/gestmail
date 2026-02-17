<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ldap\CitoyenLdap;
use App\Ldap\LdapCitoyenRepository;
use App\Ldap\UserHandler;
use App\Models\Citoyen;
use Illuminate\Console\Command;
use Str;
use Symfony\Component\Console\Command\Command as SfCommand;

final class SyncUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync citoyens database with ldap';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        foreach ($this->ldapCitoyenRepository->getAll() as $citoyenLdap) {
            if (!$citoyenLdap->getFirstAttribute('mail')) {
                continue;
            }
            $username = $citoyenLdap->getFirstAttribute('uid');
            if (!$user = Citoyen::where('uid', $username)->first()) {
                $this->addUser($citoyenLdap);
            } else {
                $this->updateUser($user, $citoyenLdap);
            }
        }

        //   $this->removeOldUsers();

        return SfCommand::SUCCESS;
    }

    private function addUser(CitoyenLdap $citoyenLdap): void
    {
        try {
            $citoyen = UserHandler::createCitoyenDbFromLdap($citoyenLdap);
            $this->info('Added'.$citoyen->uid);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    private function updateUser(Citoyen $citoyen, CitoyenLdap $citoyenLdap): void
    {
        $citoyen->update(Citoyen::generateDataFromLdap($citoyenLdap));
    }

    private function removeOldUsers(): void
    {
        $ldapUsernames = [];

        foreach (CitoyenLdap::all() as $citoyenLdap) {
            $ldapUsernames[] = $citoyenLdap->getFirstAttribute('uid');
        }

        if (count($ldapUsernames) > 200) {
            foreach (Citoyen::all() as $citoyen) {
                if (!in_array($citoyen->username, $ldapUsernames)) {
                    // $user->delete();
                    $this->info('Removed from citoyen'.$citoyen->uid);
                }
            }
        }
    }

}
