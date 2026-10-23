<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NotificationEnt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des notifications de l'ENT. L'auteur de l'action n'est jamais notifié de sa propre action,
 * et une panne du serveur de messagerie ne fait pas échouer l'action métier.
 */
class Notificateur
{
    /** @param User|iterable<User> $destinataires */
    public static function envoyer(User|iterable $destinataires, string $categorie, string $titre, string $message, ?string $lien = null, bool $parMail = false): void
    {
        $liste = ($destinataires instanceof User ? collect([$destinataires]) : Collection::wrap($destinataires))
            ->filter(fn ($u) => $u instanceof User && $u->actif && $u->id !== auth()->id())
            ->unique('id');

        foreach ($liste as $user) {
            try {
                $user->notify(new NotificationEnt($categorie, $titre, $message, $lien, $parMail));
            } catch (\Throwable $e) {
                // Le serveur de mail peut être indisponible : on garde au moins la notification dans l'ENT.
                Log::warning('Notification non envoyée par e-mail : '.$e->getMessage(), ['user' => $user->id]);
                if ($parMail) {
                    try {
                        $user->notify(new NotificationEnt($categorie, $titre, $message, $lien, false));
                    } catch (\Throwable) {
                    }
                }
            }
        }
    }
}
