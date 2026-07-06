<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\search;

final class ChangeUserPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:change-password';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change le mot de passe d\'un utilisateur';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = search(
            label: 'Quel utilisateur ?',
            options: function (string $value): array {
                if (mb_strlen($value) < 2) {
                    return [];
                }

                return User::query()
                    ->where('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->get()
                    ->mapWithKeys(fn (User $user) => [$user->id => $user->fullName()])
                    ->toArray();
            },
            placeholder: 'Rechercher par nom ou email...',
        );

        $user = User::findOrFail($userId);

        $newPassword = password(
            label: "Nouveau mot de passe pour {$user->fullName()}",
            required: true,
            validate: function (string $value): ?string {
                $validator = Validator::make(
                    ['password' => $value],
                    ['password' => Password::defaults()]
                );

                if ($validator->fails()) {
                    return 'Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule et un chiffre';
                }

                return null;
            }
        );

        $user->update(['password' => $newPassword]);

        $this->info("Mot de passe modifié pour {$user->fullName()}.");

        return Command::SUCCESS;
    }
}
