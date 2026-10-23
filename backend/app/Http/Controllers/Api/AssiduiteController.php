<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Emargement;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\AssiduiteService;
use App\Services\Journal;
use App\Services\Notificateur;
use Illuminate\Support\Facades\Storage;
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

        $etudiant = User::find($emargement->user_id);
        $quand = $seance->date->locale('fr')->isoFormat('dddd D MMMM').($seance->creneau === 'matin' ? ' (matin)' : ' (après-midi)');
        Journal::ecrire($donnees['justifiee'] ? 'absence.justification' : 'absence.annulation', $emargement,
            ($donnees['justifiee'] ? 'Absence justifiée' : 'Justification annulée').' pour '.$etudiant?->nomComplet().' le '.$quand,
            ['motif' => $emargement->motif]);
        if ($donnees['justifiee']) {
            Notificateur::envoyer($etudiant, 'absence', 'Absence justifiée', 'Votre absence du '.$quand.' est désormais justifiée.', '/ent/etudiant/assiduite');
        }

        return response()->json(['id' => $emargement->id, 'statut' => $emargement->statut, 'motif' => $emargement->motif]);
    }

    /**
     * L'apprenant joint un justificatif à l'une de ses absences (certificat, convocation…).
     * Le fichier rejoint son espace « Mes rendus » ; l'équipe le retrouve dans la fiche de l'apprenant et valide en un clic.
     */
    public function deposerJustificatif(Request $request, Emargement $emargement)
    {
        $user = $request->user();
        abort_unless($emargement->user_id === $user->id, 403);
        abort_if($emargement->statut === Emargement::PRESENT, 422, 'Cette demi-journée a été signée : aucun justificatif n\'est nécessaire.');
        $request->validate(['fichier' => ['required', 'file', 'max:5120', 'mimes:pdf,png,jpg,jpeg']], [
            'fichier.mimes' => 'Formats acceptés : PDF, PNG ou JPEG.', 'fichier.max' => 'Le justificatif ne doit pas dépasser 5 Mo.',
        ]);

        $seance = $emargement->seance()->with(['promotion', 'formateur'])->firstOrFail();
        $quand = $seance->date->locale('fr')->isoFormat('D MMMM YYYY').($seance->creneau === 'matin' ? ' (matin)' : ' (après-midi)');
        if ($ancien = $emargement->justificatif) {
            Storage::disk('local')->delete($ancien->chemin);
            $ancien->delete();
        }
        $fichier = $request->file('fichier');
        $document = Document::create([
            'promotion_id' => $seance->promotion_id, 'module_id' => $seance->module_id, 'auteur_id' => $user->id,
            'espace' => Document::RENDUS, 'titre' => mb_substr('Justificatif d\'absence - '.$quand, 0, 150),
            'chemin' => $fichier->store('documents/'.$seance->promotion_id, 'local'),
            'nom_original' => $fichier->getClientOriginalName(), 'mime' => $fichier->getMimeType() ?? 'application/octet-stream',
            'taille' => $fichier->getSize(),
        ]);
        $emargement->forceFill(['justificatif_id' => $document->id])->save();

        Journal::ecrire('justificatif.depot', $emargement, "Justificatif déposé par {$user->nomComplet()} pour le {$quand}");
        Notificateur::envoyer($seance->formateur, 'absence', 'Justificatif à valider', $user->nomComplet().' a déposé un justificatif pour le '.$quand.'.', '/ent/formateur/assiduite');

        return response()->json(['justificatif' => ['id' => $document->id, 'nom' => $document->nom_original, 'url' => '/api/documents/'.$document->id.'/apercu']], 201);
    }
}
