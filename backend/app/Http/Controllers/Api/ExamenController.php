<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvaluationCompetence;
use App\Models\Role;
use App\Models\SessionExamen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Planification des examens, composition des jurys et grilles de compétences. */
class ExamenController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return SessionExamen::with(['promotion:id,libelle,formation_id', 'jury:id,prenom,nom'])
            ->when(! $user->hasRole(Role::ADMIN), fn ($q) => $q->whereHas('jury', fn ($j) => $j->whereKey($user->id)))
            ->orderBy('debut')->get();
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'promotion_id' => ['required', 'exists:promotions,id'],
            'intitule' => ['required', 'string', 'max:150'],
            'debut' => ['required', 'date', 'after:now'],
            'fin' => ['required', 'date', 'after:debut'],
            'lieu' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json(SessionExamen::create($donnees), 201);
    }

    /** Compose le jury : un président obligatoire, au moins 2 membres, que des formateurs/admins. */
    public function jury(Request $request, SessionExamen $session)
    {
        $donnees = $request->validate([
            'membres' => ['required', 'array', 'min:2'],
            'membres.*.user_id' => ['required', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role_id',
                DB::table('roles')->whereIn('code', [Role::FORMATEUR, Role::ADMIN])->pluck('id')))],
            'membres.*.fonction' => ['required', 'in:president,membre'],
        ]);

        $presidents = collect($donnees['membres'])->where('fonction', 'president')->count();
        abort_if($presidents !== 1, 422, 'Le jury doit comporter exactement un président.');

        $session->jury()->sync(collect($donnees['membres'])->mapWithKeys(fn ($m) => [$m['user_id'] => ['fonction' => $m['fonction']]]));

        return response()->json($session->load('jury:id,prenom,nom'));
    }

    /** Grille : compétences de la formation × étudiants de la promotion, avec niveaux saisis. */
    public function grille(Request $request, SessionExamen $session)
    {
        $this->autoriserJury($request, $session);
        $promotion = $session->promotion()->with('formation.competences')->first();

        return response()->json([
            'session' => $session->only(['id', 'intitule', 'debut', 'fin']),
            'niveaux' => EvaluationCompetence::NIVEAUX,
            'competences' => $promotion->formation->competences->map->only(['id', 'code', 'libelle']),
            'etudiants' => $promotion->etudiants()->orderBy('nom')->get()->map(fn ($e) => ['id' => $e->id, 'nom' => $e->nomComplet()]),
            'evaluations' => $session->evaluations()->get(['etudiant_id', 'competence_id', 'niveau', 'commentaire']),
        ]);
    }

    public function evaluer(Request $request, SessionExamen $session)
    {
        $this->autoriserJury($request, $session);
        $promotion = $session->promotion;

        $donnees = $request->validate([
            'etudiant_id' => ['required', Rule::exists('inscriptions', 'user_id')->where('promotion_id', $promotion->id)],
            'competence_id' => ['required', Rule::exists('competences', 'id')->where('formation_id', $promotion->formation_id)],
            'niveau' => ['required', Rule::in(EvaluationCompetence::NIVEAUX)],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $evaluation = EvaluationCompetence::updateOrCreate(
            ['session_examen_id' => $session->id, 'etudiant_id' => $donnees['etudiant_id'], 'competence_id' => $donnees['competence_id']],
            ['niveau' => $donnees['niveau'], 'commentaire' => $donnees['commentaire'] ?? null, 'evaluateur_id' => $request->user()->id],
        );

        return response()->json($evaluation, 200);
    }

    private function autoriserJury(Request $request, SessionExamen $session): void
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::ADMIN) || $session->jury()->whereKey($user->id)->exists(), 403, 'Réservé aux membres du jury.');
    }
}
