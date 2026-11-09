<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the recovery email of a citoyen who forgot the password of their mailbox. The link
 * logs them in on the password change page.
 */
final class CitoyenRecoveryLink extends Notification
{
    use Queueable;

    public function __construct(public string $mail, public string $url, public int $expiresInMinutes) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Récupération de votre mot de passe')
            ->greeting('Bonjour,')
            ->line("Une demande de récupération du mot de passe a été faite pour la boîte {$this->mail}.")
            ->action('Choisir un nouveau mot de passe', $this->url)
            ->line("Ce lien est valable {$this->expiresInMinutes} minutes et ne fonctionne qu'une seule fois.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.");
    }
}
