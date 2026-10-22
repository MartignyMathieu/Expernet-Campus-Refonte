<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormationResource;
use App\Models\Filiere;
use App\Models\Formation;
use Illuminate\Http\Request;

/** Module public : catalogue avec recherche à facettes (filière, niveau RNCP, CPF). */
class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'filiere' => ['nullable', 'string', 'exists:filieres,code'],
            'niveau' => ['nullable', 'integer', 'in:5,6,7'],
            'cpf' => ['nullable', 'boolean'],
        ]);

        $formations = Formation::query()->publiee()->with('filiere')
            ->when($filtres['q'] ?? null, function ($q, $terme) {
                $like = '%'.mb_strtolower($terme).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(intitule) LIKE ?', [$like])->orWhereRaw('LOWER(accroche) LIKE ?', [$like]));
            })
            ->when($filtres['filiere'] ?? null, fn ($q, $code) => $q->whereHas('filiere', fn ($f) => $f->where('code', $code)))
            ->when($filtres['niveau'] ?? null, fn ($q, $n) => $q->where('niveau_rncp', $n))
            ->when(array_key_exists('cpf', $filtres) && $filtres['cpf'] !== null, fn ($q) => $q->where('eligible_cpf', (bool) $filtres['cpf']))
            ->orderBy('niveau_rncp')->orderBy('intitule')
            ->get();

        return FormationResource::collection($formations);
    }

    public function show(Formation $formation)
    {
        abort_unless($formation->publiee, 404);

        return new FormationResource($formation->load(['filiere', 'elements', 'campus']));
    }

    /** Valeurs disponibles pour construire les facettes côté front. */
    public function facettes()
    {
        return response()->json([
            'filieres' => Filiere::orderBy('libelle')->get(['code', 'libelle']),
            'niveaux' => [
                ['valeur' => 5, 'libelle' => 'Niveau 5 (Bac+2)'],
                ['valeur' => 6, 'libelle' => 'Niveau 6 (Bac+3/4)'],
                ['valeur' => 7, 'libelle' => 'Niveau 7 (Bac+5)'],
            ],
        ]);
    }
}
