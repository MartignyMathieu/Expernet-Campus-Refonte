<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Emargement;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\AssiduiteService;
use Illuminate\Http\Request;

/** Tableau de bord formateur : promotions suivies, taux d'assiduité et alertes. */
class AssiduiteController extends Controller
{
    public function __construct(private readonly AssiduiteService $service) {}

    public function mesPromotions(Request $request)
    {
        $user = $request->user();
        $promotions = $user->hasRole(Role::ADMIN)
            ? Promotion::with('formation:id,intitule')->get()
            : Promotion::with('formation:id,intitule')->whereHas('seances', fn ($q) => $q->where('formateur_id', $user->id))->get();

        return response()->json($promotions->map(fn (Promotion $p) => $this->service->tableauPromotion($p) + [
            'formation' => $p->formation->intitule,
        ])->values());
    }

    public function promotion(Request $request, Promotion $promotion)
    {
        abort_unless($request->user()->peutVoirPromotion($promotion) && ! $request->user()->hasRole(Role::ETUDIANT), 403);

        return response()->json($this->service->tableauPromotion($promotion));
    }

    /** Assiduité personnelle de l'apprenant (toutes ses promotions). */
    public function moi(Request $request)
    {
        $user = $request->user();

        return response()->json($user->promotions()->get()->map(fn (Promotion $p) => $this->service->bilanEtudiant($user, $p))->values());
    }

    /** Détail d'un apprenant pour l'équipe pédagogique (absences à justifier). */
    public function etudiant(Request $request, Promotion $promotion, User $etudiant)
    {
        abort_unless($request->user()->peutVoirPromotion($promotion) && ! $request->user()->hasRole(Role::ETUDIANT), 403);
        abort_unless($promotion->etudiants()->whereKey($etudiant->id)->exists(), 404);

        return response()->json($this->service->bilanEtudiant($etudiant, $promotion) + [
            'etudiant' => ['id' => $etudiant->id, 'nom' => $etudiant->nomComplet(), 'email' => $etudiant->email, 'telephone' => $etudiant->telephone, 'avatar_url' => $etudiant->avatarUrl()],
        ]);
    }

    /** Justifier (ou dé-justifier) une absence : absent ⇄ excusé, avec motif et traçabilité. */
    public function justifier(Request $request, Emargement $emargement)
    {
        $user = $request->user();
        $seance = $emargement->seance()->with('promotion')->firstOrFail();
        abort_unless($user->peutVoirPromotion($seance->promotion) && ! $user->hasRole(Role::ETUDIANT), 403);
        abort_if($emargement->statut === Emargement::PRESENT, 422, 'Cette demi-journée a été signée : rien à justifier.');

        $donnees = $request->validate(['justifiee' => ['required', 'boolean'], 'motif' => ['nullable', 'string', 'max:255']]);
        $emargement->forceFill($donnees['justifiee']
            ? ['statut' => Emargement::EXCUSE, 'motif' => isset($donnees['motif']) ? strip_tags($donnees['motif']) : null, 'justifie_par' => $user->id, 'justifie_at' => now()]
            : ['statut' => Emargement::ABSENT, 'motif' => null, 'justifie_par' => null, 'justifie_at' => null])->save();

        return response()->json(['id' => $emargement->id, 'statut' => $emargement->statut, 'motif' => $emargement->motif]);
    }
}
