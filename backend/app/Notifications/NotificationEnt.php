<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification de l'ENT : toujours enregistrée en base (cloche de l'ENT),
 * doublée d'un e-mail pour les évènements importants (note, devoir, correction).
 */
class NotificationEnt extends Notification
{
    public function __construct(
        public string $categorie,
        public string $titre,
        public string $message,
        public ?string $lien = null,
        public bool $parMail = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->parMail && ! empty($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['categorie' => $this->categorie, 'titre' => $this->titre, 'message' => $this->message, 'lien' => $this->lien];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->titre.' - Expernet Campus')
            ->greeting('Bonjour '.($notifiable->prenom ?? '').',')
            ->line($this->message);

        if ($this->lien) {
            $mail->action('Ouvrir dans mon espace', rtrim((string) config('app.url'), '/').$this->lien);
        }

        return $mail->salutation('L\'équipe Expernet Campus');
    }
}
