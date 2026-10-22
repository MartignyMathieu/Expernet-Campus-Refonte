<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Promotion;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Espace documentaire : dépôt par le formateur, consultation par la promotion. */
class DocumentController extends Controller
{
    public function index(Request $request, Promotion $promotion)
    {
        abort_unless($request->user()->peutVoirPromotion($promotion), 403);

        return Document::with(['auteur:id,prenom,nom', 'module:id,intitule'])
            ->where('promotion_id', $promotion->id)->latest()->get();
    }

    public function store(Request $request, Promotion $promotion)
    {
        $user = $request->user();
        abort_unless($user->peutVoirPromotion($promotion) && ! $user->hasRole(Role::ETUDIANT), 403);

        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'module_id' => ['nullable', 'exists:modules,id'],
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,docx,pptx,xlsx,zip,png,jpg,txt,md'],
        ]);

        $fichier = $request->file('fichier');
        $document = Document::create([
            'promotion_id' => $promotion->id,
            'module_id' => $donnees['module_id'] ?? null,
            'auteur_id' => $user->id,
            'titre' => $donnees['titre'],
            'chemin' => $fichier->store('documents/'.$promotion->id, 'local'),
            'nom_original' => $fichier->getClientOriginalName(),
            'mime' => $fichier->getMimeType() ?? 'application/octet-stream',
            'taille' => $fichier->getSize(),
        ]);

        return response()->json($document, 201);
    }

    public function telecharger(Request $request, Document $document)
    {
        abort_unless($request->user()->peutVoirPromotion(Promotion::findOrFail($document->promotion_id)), 403);

        return Storage::disk('local')->download($document->chemin, $document->nom_original);
    }
}
