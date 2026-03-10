<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class MailTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie un mail de test pour vérifier la configuration du mailer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $to = $this->argument('to');
        $mailer = config('mail.default');
        $from = config('mail.from.address');
        $host = config("mail.mailers.{$mailer}.host");
        $port = config("mail.mailers.{$mailer}.port");

        $this->info("Mailer: {$mailer}");
        $this->info("From: {$from}");
        $this->info("Host: {$host}:{$port}");
        $this->info("To: {$to}");
        $this->newLine();

        try {
            Mail::raw('Ceci est un mail de test envoyé depuis Gestmail.', function ($message) use ($to): void {
                $message->to($to)
                    ->subject('Gestmail - Test mailer');
            });

            $this->info('Mail envoyé avec succès.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Erreur lors de l\'envoi: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
