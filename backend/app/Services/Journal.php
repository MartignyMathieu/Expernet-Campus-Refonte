<?php

namespace App\Services;

use App\Models\JournalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Journal d'audit : qui a fait quoi, sur quel objet, quand et depuis quelle adresse.
 * Écrit pour les actions sensibles (notes, absences, documents, comptes, double authentification…).
 * Une erreur d'écriture du journal ne doit jamais bloquer l'action métier : elle est consignée dans les logs.
 */
class Journal
{
    public static function ecrire(string $action, ?Model $cible, string $resume, array $details = []): void
    {
        try {
            JournalAudit::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'cible_type' => $cible ? class_basename($cible) : null,
                'cible_id' => $cible?->getKey(),
                'resume' => mb_substr($resume, 0, 255),
                'details' => $details ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Journal d\'audit indisponible : '.$e->getMessage(), ['action' => $action]);
        }
    }
}
