<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    /** Étudiant : ses notes, avec moyenne pondérée. */
    public function mesNotes(Request $request)
    {
        $notes = Note::with('module:id,intitule,code')->where('etudiant_id', $request->user()->id)
            ->orderByDesc('date_evaluation')->get();

        $sommeCoef = $notes->sum(fn ($n) => (float) $n->coefficient);
        $moyenne = $sommeCoef > 0 ? round($notes->sum(fn ($n) => $n->valeur * $n->coefficient) / $sommeCoef, 2) : null;

        return response()->json(['moyenne' => $moyenne, 'notes' => $notes]);
    }

    /** Formateur : saisie d'une note pour un module qu'il enseigne à la promotion de l'étudiant. */
    public function store(Request $request)
    {
        $donnees = $request->validate([
            'module_id' => ['required', 'exists:modules,id'],
            'etudiant_id' => ['required', Rule::exists('users', 'id')],
            'intitule' => ['required', 'string', 'max:150'],
            'valeur' => ['required', 'numeric', 'between:0,20'],
            'coefficient' => ['nullable', 'numeric', 'between:0.5,10'],
            'date_evaluation' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $formateur = $request->user();
        $etudiant = User::findOrFail($donnees['etudiant_id']);
        $enseigne = Module::whereKey($donnees['module_id'])
            ->whereExists(fn ($q) => $q->from('seances')->whereColumn('seances.module_id', 'modules.id')
                ->where('seances.formateur_id', $formateur->id)
                ->whereIn('seances.promotion_id', $etudiant->promotions()->pluck('promotions.id')))
            ->exists();
        abort_unless($enseigne, 403, 'Vous n\'enseignez pas ce module à cet étudiant.');

        $note = Note::create($donnees + ['formateur_id' => $formateur->id, 'coefficient' => $donnees['coefficient'] ?? 1]);

        return response()->json($note, 201);
    }
}
