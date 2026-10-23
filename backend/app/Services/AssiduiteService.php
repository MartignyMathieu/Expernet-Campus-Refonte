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
                'avatar_url' => $e->avatarUrl(),
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

    /**
     * Bilan personnel d'un apprenant : taux, décompte par statut et détail des absences.
     *
     * @return array{promotion: array, taux: ?float, seuil: int, presences: int, absences: int, excuses: int, detail_absences: array}
     */
    public function bilanEtudiant(User $etudiant, Promotion $promotion): array
    {
        $emargements = Emargement::with('seance.module:id,intitule')
            ->where('user_id', $etudiant->id)
            ->whereHas('seance', fn ($q) => $q->where('promotion_id', $promotion->id)->whereNotNull('cloturee_at'))
            ->get();
        $compte = fn (string $statut) => $emargements->where('statut', $statut)->count();

        return [
            'promotion' => ['id' => $promotion->id, 'libelle' => $promotion->libelle],
            'taux' => $this->tauxEtudiant($etudiant, $promotion),
            'seuil' => (int) config('expernet.assiduite.seuil_alerte'),
            'presences' => $compte(Emargement::PRESENT),
            'absences' => $compte(Emargement::ABSENT),
            'excuses' => $compte(Emargement::EXCUSE),
            'detail_absences' => $emargements->whereIn('statut', [Emargement::ABSENT, Emargement::EXCUSE])
                ->sortByDesc(fn ($e) => $e->seance->date.$e->seance->creneau)
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'date' => \Illuminate\Support\Carbon::parse($e->seance->date)->toDateString(),
                    'creneau' => $e->seance->creneau,
                    'module' => $e->seance->module?->intitule,
                    'statut' => $e->statut,
                    'motif' => $e->motif,
                ])->values()->all(),
        ];
    }
}
