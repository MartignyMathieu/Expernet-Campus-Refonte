<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Seance;
use Illuminate\Http\Request;

/** Emploi du temps : séances de l'étudiant (via ses promotions) ou du formateur (séances animées). */
class PlanningController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ]);
        $du = $periode['du'] ?? now()->startOfWeek()->toDateString();
        $au = $periode['au'] ?? now()->addWeeks(2)->endOfWeek()->toDateString();
        $user = $request->user();

        $seances = Seance::query()
            ->with(['module:id,intitule,code', 'formateur:id,prenom,nom', 'promotion:id,libelle'])
            ->whereDate('date', '>=', $du)->whereDate('date', '<=', $au)
            ->when($user->hasRole(Role::ETUDIANT), fn ($q) => $q->whereIn('promotion_id', $user->promotions()->pluck('promotions.id')))
            ->when($user->hasRole(Role::FORMATEUR), fn ($q) => $q->where('formateur_id', $user->id))
            ->orderBy('date')->orderBy('heure_debut')
            ->get();

        $mesEmargements = $user->hasRole(Role::ETUDIANT)
            ? $user->emargements()->whereIn('seance_id', $seances->pluck('id'))->get()->keyBy('seance_id')
            : collect();

        return response()->json(['du' => $du, 'au' => $au, 'seances' => $seances->map(fn (Seance $s) => [
            'id' => $s->id,
            'date' => $s->date->toDateString(),
            'creneau' => $s->creneau,
            'heure_debut' => substr($s->heure_debut, 0, 5),
            'heure_fin' => substr($s->heure_fin, 0, 5),
            'lieu' => $s->lieu,
            'module' => $s->module->intitule,
            'formateur' => $s->formateur->nomComplet(),
            'promotion' => $s->promotion->libelle,
            'emargement' => [
                'ouvert' => $s->fenetreOuverte(),
                'cloture' => $s->cloturee_at !== null,
                'mon_statut' => $mesEmargements->get($s->id)?->statut,
            ],
        ])->values()]);
    }
}
