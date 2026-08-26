<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Il n'y a pas de frontend web pour cliquer un lien de réinitialisation
 * (l'app admin est un client Flutter) : on envoie le token en clair, à
 * saisir manuellement dans l'app avec le nouveau mot de passe.
 */
class AdminPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Voici votre code de réinitialisation de mot de passe : {$this->token}")
            ->line("Saisissez ce code dans l'application avec votre nouveau mot de passe.")
            ->line("Ce code expire dans {$expireMinutes} minutes.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.");
    }
}
