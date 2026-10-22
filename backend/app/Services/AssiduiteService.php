<?php

namespace App\Services;

use App\Models\Emargement;
use App\Models\Promotion;
use App\Models\User;

/**
 * Indicateurs d'assiduité calculés sur les séances clôturées uniquement.
 * Taux = (présent + excusé) / séances clôturées. Alerte si taux < seuil configuré.
 */
class AssiduiteService
{
    public function tauxEtudiant(User $etudiant, Promotion $promotion): ?float
    {
        $stats = Emargement::query()
            ->join('seances', 'seances.id', '=', 'emargements.seance_id')
            ->where('seances.promotion_id', $promotion->id)
            ->whereNotNull('seances.cloturee_at')
            ->where('emargements.user_id', $etudiant->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN emargements.statut IN ('present','excuse') THEN 1 ELSE 0 END) as presents")
            ->first();

        if (! $stats || (int) $stats->total === 0) {
            return null;
        }

        return round(((int) $stats->presents / (int) $stats->total) * 100, 1);
    }

    /** @return array{promotion: array, taux_moyen: ?float, seuil: int, etudiants: array, alertes: array} */
    public function tableauPromotion(Promotion $promotion): array
    {
        $seuil = (int) config('expernet.assiduite.seuil_alerte');
        $etudiants = $promotion->etudiants()->orderBy('nom')->get();

        $lignes = $etudiants->map(function (User $e) use ($promotion, $seuil) {
            $taux = $this->tauxEtudiant($e, $promotion);
            $absences = Emargement::where('user_id', $e->id)->where('statut', Emargement::ABSENT)
                ->whereHas('seance', fn ($q) => $q->where('promotion_id', $promotion->id))->count();

            return [
                'id' => $e->id,
                'nom' => $e->nomComplet(),
                'taux' => $taux,
                'absences' => $absences,
                'alerte' => $taux !== null && $taux < $seuil,
            ];
        })->values();

        $avecTaux = $lignes->whereNotNull('taux');

        return [
            'promotion' => ['id' => $promotion->id, 'libelle' => $promotion->libelle],
            'taux_moyen' => $avecTaux->isEmpty() ? null : round($avecTaux->avg('taux'), 1),
            'seuil' => $seuil,
            'etudiants' => $lignes->all(),
            'alertes' => $lignes->where('alerte', true)->values()->all(),
        ];
    }
}
