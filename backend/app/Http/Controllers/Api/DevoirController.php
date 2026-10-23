<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devoir;
use App\Models\Document;
use App\Models\Note;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Seance;
use App\Models\User;
use App\Services\Journal;
use App\Services\Notificateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Devoirs : le formateur publie un devoir (consignes, échéance, coefficient) pour un module qu'il enseigne ;
 * l'apprenant dépose son rendu (remplaçable tant qu'il n'est pas corrigé) ; la correction (note + commentaire)
 * est reportée automatiquement dans ses notes.
 */
class DevoirController extends Controller
{
    public function index(Request $request, Promotion $promotion)
    {
        $user = $request->user();
        abort_unless($user->peutVoirPromotion($promotion), 403);
        $etudiant = $user->hasRole(Role::ETUDIANT);
        $effectif = $promotion->etudiants()->count();

        $devoirs = Devoir::with(['module:id,intitule,code', 'auteur:id,prenom,nom'])
            ->where('promotion_id', $promotion->id)->orderByDesc('echeance')->get();
        $rendus = Document::whereIn('devoir_id', $devoirs->pluck('id'))
            ->when($etudiant, fn ($q) => $q->where('auteur_id', $user->id))->get()->groupBy('devoir_id');

        return response()->json($devoirs->map(fn (Devoir $d) => $this->resume($d) + ($etudiant
            ? ['mon_rendu' => ($r = $rendus->get($d->id)?->first()) ? $this->formatRendu($r, $d) : null]
            : ['nb_rendus' => $rendus->get($d->id)?->count() ?? 0, 'nb_corriges' => $rendus->get($d->id)?->whereNotNull('corrige_at')->count() ?? 0, 'effectif' => $effectif]
        ))->values());
    }

    public function store(Request $request, Promotion $promotion)
    {
        $user = $request->user();
        abort_if($user->hasRole(Role::ETUDIANT) || ! $user->peutVoirPromotion($promotion), 403);

        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'consignes' => ['nullable', 'string', 'max:5000'],
            'module_id' => ['required', Rule::exists('modules', 'id')->where('formation_id', $promotion->formation_id)],
            'echeance' => ['required', 'date', 'after:now'],
            'coefficient' => ['nullable', 'numeric', 'between:0.5,10'],
        ], ['echeance.after' => 'L\'échéance doit être dans le futur.']);

        abort_unless($this->enseigne($user, $promotion->id, (int) $donnees['module_id']), 403, 'Vous n\'enseignez pas ce module à cette promotion.');

        $devoir = Devoir::create([
            'promotion_id' => $promotion->id, 'module_id' => $donnees['module_id'], 'auteur_id' => $user->id,
            'titre' => $donnees['titre'], 'consignes' => isset($donnees['consignes']) ? strip_tags($donnees['consignes']) : null,
            'echeance' => $donnees['echeance'], 'coefficient' => $donnees['coefficient'] ?? 1,
        ]);

        Journal::ecrire('devoir.creation', $devoir, "Devoir « {$devoir->titre} » publié pour {$promotion->libelle}", ['echeance' => $devoir->echeance->toIso8601String()]);
        Notificateur::envoyer($promotion->etudiants()->get(), 'devoir', 'Nouveau devoir : '.$devoir->titre,
            'À rendre avant le '.$devoir->echeance->locale('fr')->isoFormat('dddd D MMMM [à] HH[h]mm').'.', '/ent/etudiant/devoirs', true);

        return response()->json($this->resume($devoir->load(['module:id,intitule,code', 'auteur:id,prenom,nom'])), 201);
    }

    /** Détail : l'équipe voit chaque apprenant de la promotion avec son rendu ; l'apprenant ne voit que le sien. */
    public function show(Request $request, Devoir $devoir)
    {
        $user = $request->user();
        abort_unless($user->peutVoirPromotion($devoir->promotion), 403);
        $devoir->load(['module:id,intitule,code', 'auteur:id,prenom,nom']);

        if ($user->hasRole(Role::ETUDIANT)) {
            $rendu = $devoir->rendus()->where('auteur_id', $user->id)->first();

            return response()->json($this->resume($devoir) + ['mon_rendu' => $rendu ? $this->formatRendu($rendu, $devoir) : null]);
        }

        $rendus = $devoir->rendus()->get()->keyBy('auteur_id');

        return response()->json($this->resume($devoir) + [
            'peut_corriger' => $this->peutCorriger($user, $devoir),
            'apprenants' => $devoir->promotion->etudiants()->orderBy('nom')->orderBy('prenom')->get()->map(fn (User $e) => [
                'id' => $e->id, 'nom' => $e->nomComplet(), 'avatar_url' => $e->avatarUrl(),
                'rendu' => ($r = $rendus->get($e->id)) ? $this->formatRendu($r, $devoir) : null,
            ])->values(),
        ]);
    }

    /** Dépôt (ou remplacement) du rendu de l'apprenant. Un rendu corrigé ne peut plus être remplacé. */
    public function rendre(Request $request, Devoir $devoir)
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::ETUDIANT) && $user->peutVoirPromotion($devoir->promotion), 403);
        $donnees = $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,docx,pptx,xlsx,odt,ods,odp,zip,png,jpg,jpeg,txt,md,csv'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $existant = $devoir->rendus()->where('auteur_id', $user->id)->first();
        abort_if($existant?->corrige_at, 422, 'Ce rendu a déjà été corrigé : il ne peut plus être remplacé.');

        $fichier = $request->file('fichier');
        $rendu = DB::transaction(function () use ($existant, $devoir, $user, $fichier, $donnees) {
            if ($existant) {
                Storage::disk('local')->delete($existant->chemin);
                $existant->delete();
            }

            return Document::create([
                'promotion_id' => $devoir->promotion_id, 'module_id' => $devoir->module_id, 'devoir_id' => $devoir->id,
                'auteur_id' => $user->id, 'espace' => Document::RENDUS,
                'titre' => mb_substr('Rendu - '.$devoir->titre, 0, 150),
                'description' => isset($donnees['commentaire']) ? strip_tags($donnees['commentaire']) : null,
                'chemin' => $fichier->store('documents/'.$devoir->promotion_id, 'local'),
                'nom_original' => $fichier->getClientOriginalName(),
                'mime' => $fichier->getMimeType() ?? 'application/octet-stream',
                'taille' => $fichier->getSize(),
            ]);
        });

        $retard = $rendu->created_at->gt($devoir->echeance);
        Notificateur::envoyer($devoir->auteur, 'rendu', ($retard ? 'Rendu en retard : ' : 'Nouveau rendu : ').$devoir->titre,
            $user->nomComplet().' a déposé son travail.', '/ent/formateur/devoirs?devoir='.$devoir->id);

        return response()->json($this->formatRendu($rendu, $devoir), $existant ? 200 : 201);
    }

    /** Correction d'un rendu : note sur 20 et commentaire ; la note est créée (ou mise à jour) dans le carnet de notes. */
    public function corriger(Request $request, Document $document)
    {
        $user = $request->user();
        $devoir = $document->devoir;
        abort_if($devoir === null, 404, 'Ce document n\'est pas un rendu de devoir.');
        abort_unless($this->peutCorriger($user, $devoir), 403, 'Vous ne pouvez pas corriger ce devoir.');

        $donnees = $request->validate([
            'note' => ['required', 'numeric', 'between:0,20'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);
        $ancienne = $document->note;

        DB::transaction(function () use ($document, $devoir, $user, $donnees) {
            $note = $document->note_id ? Note::find($document->note_id) : null;
            $valeurs = [
                'module_id' => $devoir->module_id, 'etudiant_id' => $document->auteur_id, 'formateur_id' => $user->id,
                'intitule' => mb_substr($devoir->titre, 0, 150), 'valeur' => $donnees['note'], 'coefficient' => $devoir->coefficient,
                'date_evaluation' => today(),
            ];
            $note ? $note->update($valeurs) : $note = Note::create($valeurs);

            $document->forceFill([
                'note' => $donnees['note'], 'commentaire' => isset($donnees['commentaire']) ? strip_tags($donnees['commentaire']) : null,
                'corrige_par' => $user->id, 'corrige_at' => now(), 'note_id' => $note->id,
            ])->save();
        });

        $etudiant = User::find($document->auteur_id);
        Journal::ecrire('rendu.correction', $document, "Correction de « {$devoir->titre} » pour {$etudiant?->nomComplet()} : {$donnees['note']}/20",
            ['ancienne_note' => $ancienne, 'nouvelle_note' => (float) $donnees['note']]);
        Notificateur::envoyer($etudiant, 'note', 'Devoir corrigé : '.$devoir->titre,
            'Votre rendu a été corrigé : '.rtrim(rtrim(number_format((float) $donnees['note'], 2, ',', ''), '0'), ',').'/20.', '/ent/etudiant/devoirs', true);

        return response()->json($this->formatRendu($document->fresh(), $devoir));
    }

    private function enseigne(User $user, int $promotionId, int $moduleId): bool
    {
        return $user->hasRole(Role::ADMIN) || Seance::where('promotion_id', $promotionId)->where('module_id', $moduleId)
            ->where('formateur_id', $user->id)->exists();
    }

    private function peutCorriger(User $user, Devoir $devoir): bool
    {
        return ! $user->hasRole(Role::ETUDIANT) && $user->hasPermission('documents.upload')
            && ($devoir->auteur_id === $user->id || $this->enseigne($user, $devoir->promotion_id, $devoir->module_id));
    }

    private function resume(Devoir $d): array
    {
        return [
            'id' => $d->id, 'promotion_id' => $d->promotion_id, 'titre' => $d->titre, 'consignes' => $d->consignes,
            'echeance' => $d->echeance->toIso8601String(), 'echu' => $d->echeance->isPast(), 'coefficient' => $d->coefficient,
            'module' => $d->module ? ['id' => $d->module->id, 'intitule' => $d->module->intitule] : null,
            'auteur' => $d->auteur ? $d->auteur->nomComplet() : null,
        ];
    }

    private function formatRendu(Document $r, Devoir $d): array
    {
        return [
            'id' => $r->id, 'nom_original' => $r->nom_original, 'taille' => $r->taille, 'commentaire_apprenant' => $r->description,
            'depose_at' => $r->created_at->toIso8601String(), 'en_retard' => $r->created_at->gt($d->echeance),
            'note' => $r->note, 'commentaire' => $r->commentaire, 'corrige_at' => $r->corrige_at?->toIso8601String(),
            'telechargement' => '/api/documents/'.$r->id.'/telechargement',
        ];
    }
}
