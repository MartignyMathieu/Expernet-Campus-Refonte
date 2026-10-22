<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Role;
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
}
