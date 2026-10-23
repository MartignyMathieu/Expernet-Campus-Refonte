<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignatureRequest;
use App\Models\Emargement;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Seance;
use App\Models\User;
use App\Services\EmargementService;
use App\Services\Journal;
use App\Services\Notificateur;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class EmargementController extends Controller
{
    public function __construct(private readonly EmargementService $service) {}

    /** Formateur : ouvre la fenêtre et récupère le code à afficher en salle. */
    public function ouvrir(Request $request, Seance $seance)
    {
        $code = $this->service->ouvrir($seance, $request->user());
        $seance->loadMissing(['promotion', 'module:id,intitule']);
        Journal::ecrire('emargement.ouverture', $seance, "Ouverture de l'émargement : {$seance->module?->intitule} ({$seance->promotion->libelle})");
        Notificateur::envoyer($seance->promotion->etudiants()->get(), 'emargement', 'Émargement ouvert',
            'Vous pouvez signer votre présence pour la séance « '.$seance->module?->intitule.' ».', '/ent/etudiant/emargement/'.$seance->id);

        return response()->json([
            'code' => $code,
            'expire_at' => $seance->fresh()->emargement_expire_at->toIso8601String(),
        ]);
    }

    /** Étudiant : signe avec le code + le tracé de sa signature. */
    public function signer(SignatureRequest $request, Seance $seance)
    {
        $emargement = $this->service->signer(
            $seance->load('promotion'),
            $request->user(),
            $request->validated('code'),
            $request->validated('signature'),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'statut' => $emargement->statut,
            'signe_at' => $emargement->signe_at->toIso8601String(),
            'empreinte' => $emargement->empreinte,
        ], 201);
    }

    public function cloturer(Request $request, Seance $seance)
    {
        $absents = $this->service->cloturer($seance->load('promotion'), $request->user());
        Journal::ecrire('emargement.cloture', $seance, "Clôture de l'émargement ({$seance->promotion->libelle}) : {$absents} ".($absents > 1 ? 'absents enregistrés' : 'absent enregistré'), ['absents' => $absents]);

        return response()->json(['message' => 'Séance clôturée.', 'absents' => $absents]);
    }

    /** Feuille d'émargement d'une séance (formateur de la séance ou admin). */
    public function feuille(Request $request, Seance $seance)
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::ADMIN) || $seance->formateur_id === $user->id, 403);

        $emargements = $seance->emargements()->get()->keyBy('user_id');

        return response()->json([
            'seance' => ['id' => $seance->id, 'date' => $seance->date->toDateString(), 'creneau' => $seance->creneau,
                'ouvert' => $seance->fenetreOuverte(), 'cloture' => $seance->cloturee_at !== null,
                'expire_at' => $seance->emargement_expire_at?->toIso8601String()],
            'lignes' => $seance->promotion->etudiants()->orderBy('nom')->get()->map(fn (User $e) => [
                'etudiant_id' => $e->id,
                'nom' => $e->nomComplet(),
                'statut' => $emargements->get($e->id)?->statut,
                'signe_at' => $emargements->get($e->id)?->signe_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Vérifie l'intégrité d'une signature (recalcul de l'empreinte). */
    public function verifier(Request $request, Emargement $emargement)
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::ADMIN) || $emargement->seance->formateur_id === $user->id || $emargement->user_id === $user->id, 403);

        return response()->json(['empreinte' => $emargement->empreinte, 'integre' => $this->service->verifier($emargement)]);
    }

    /** Attestation d'assiduité PDF : l'étudiant pour lui-même, l'admin pour n'importe qui. */
    public function attestation(Request $request, Promotion $promotion, ?User $etudiant = null)
    {
        $user = $request->user();
        $etudiant ??= $user;
        abort_unless($etudiant->id === $user->id || $user->hasRole(Role::ADMIN), 403);
        abort_unless($promotion->etudiants()->whereKey($etudiant->id)->exists(), 404);

        $lignes = Emargement::with('seance.module')
            ->where('user_id', $etudiant->id)
            ->whereHas('seance', fn ($q) => $q->where('promotion_id', $promotion->id))
            ->get()->sortBy(fn ($e) => $e->seance->date->toDateString().$e->seance->heure_debut);

        $pdf = Pdf::loadView('pdf.attestation', [
            'etudiant' => $etudiant,
            'promotion' => $promotion->load('formation'),
            'lignes' => $lignes,
            'presents' => $lignes->where('statut', Emargement::PRESENT)->count(),
            'genere_le' => now(),
        ]);

        return $pdf->download(sprintf('attestation-assiduite-%d-%s.pdf', $etudiant->id, now()->format('Ymd')));
    }
}
