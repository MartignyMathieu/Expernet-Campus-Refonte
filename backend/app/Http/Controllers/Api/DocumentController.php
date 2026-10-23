<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Module;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\Journal;
use App\Services\Notificateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Espace documentaire d'une promotion, organisé en espaces (cours, pédagogie, TP, examens, administratif,
 * stage, rendus). L'équipe pédagogique publie ; les apprenants consultent et déposent leurs rendus,
 * visibles d'eux seuls et de l'équipe.
 */
class DocumentController extends Controller
{
    /** Espaces disponibles et droit de dépôt de l'utilisateur dans chacun. */
    public function espaces(Request $request)
    {
        $role = $request->user()->role->code;

        return response()->json(collect(Document::ESPACES)->map(fn (array $e, string $code) => [
            'code' => $code,
            'libelle' => $code === Document::RENDUS && $role !== Role::ETUDIANT ? 'Rendus des apprenants' : $e['libelle'],
            'description' => $e['description'],
            'icone' => $e['icone'],
            'peut_deposer' => Document::peutDeposer($role, $code) && $request->user()->hasPermission($code === Document::RENDUS ? 'documents.rendre' : 'documents.upload'),
        ])->values());
    }

    /** Modules de la formation suivie par la promotion (rattachement et filtre des documents). */
    public function modules(Request $request, Promotion $promotion)
    {
        abort_unless($request->user()->peutVoirPromotion($promotion), 403);

        return response()->json(Module::where('formation_id', $promotion->formation_id)->orderBy('id')->get(['id', 'code', 'intitule']));
    }

    public function index(Request $request, Promotion $promotion)
    {
        $user = $request->user();
        abort_unless($user->peutVoirPromotion($promotion), 403);
        $request->validate(['espace' => ['nullable', Rule::in(array_keys(Document::ESPACES))]]);

        return Document::with(['auteur:id,prenom,nom,avatar_path', 'module:id,intitule'])
            ->where('promotion_id', $promotion->id)
            ->when($request->query('espace'), fn ($q, $espace) => $q->where('espace', $espace))
            // Rendus : un apprenant ne voit que les siens
            ->when($user->hasRole(Role::ETUDIANT), fn ($q) => $q->where(fn ($q) => $q
                ->where('espace', '!=', Document::RENDUS)->orWhere('auteur_id', $user->id)))
            ->latest()->get()
            ->each(fn (Document $d) => $d->auteur?->setAttribute('avatar_url', $d->auteur->avatarUrl()))
            ->map(fn (Document $d) => $d->setAttribute('peut_supprimer', $this->peutSupprimer($user, $d)));
    }

    public function store(Request $request, Promotion $promotion)
    {
        $user = $request->user();
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'espace' => ['nullable', Rule::in(array_keys(Document::ESPACES))],
            'module_id' => ['nullable', Rule::exists('modules', 'id')->where('formation_id', $promotion->formation_id)],
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,docx,pptx,xlsx,odt,ods,odp,zip,png,jpg,jpeg,txt,md,csv'],
        ]);
        $espace = $donnees['espace'] ?? 'cours';
        $permission = $espace === Document::RENDUS ? 'documents.rendre' : 'documents.upload';

        abort_unless($user->peutVoirPromotion($promotion) && $user->hasPermission($permission)
            && Document::peutDeposer($user->role->code, $espace), 403, 'Vous ne pouvez pas déposer dans cet espace.');

        $fichier = $request->file('fichier');
        $document = Document::create([
            'promotion_id' => $promotion->id,
            'module_id' => $donnees['module_id'] ?? null,
            'auteur_id' => $user->id,
            'espace' => $espace,
            'titre' => $donnees['titre'],
            'description' => isset($donnees['description']) ? strip_tags($donnees['description']) : null,
            'chemin' => $fichier->store('documents/'.$promotion->id, 'local'),
            'nom_original' => $fichier->getClientOriginalName(),
            'mime' => $fichier->getMimeType() ?? 'application/octet-stream',
            'taille' => $fichier->getSize(),
        ]);

        if ($espace !== Document::RENDUS) {
            Notificateur::envoyer($promotion->etudiants()->get(), 'document', 'Nouveau document : '.$document->titre,
                'Publié dans « '.Document::ESPACES[$espace]['libelle'].' » par '.$user->nomComplet().'.', '/ent/etudiant/documents?espace='.$espace);
        }

        return response()->json($document, 201);
    }

    /** Suppression : l'auteur ou un administrateur. */
    public function destroy(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($this->peutSupprimer($user, $document) && $this->peutConsulter($user, $document), 403);

        Journal::ecrire('document.suppression', $document, "Suppression du document « {$document->titre} » ({$document->espace})", ['nom_original' => $document->nom_original]);
        Storage::disk('local')->delete($document->chemin);
        $document->delete();

        return response()->noContent();
    }

    public function telecharger(Request $request, Document $document)
    {
        abort_unless($this->peutConsulter($request->user(), $document), 403);

        return Storage::disk('local')->download($document->chemin, $document->nom_original);
    }

    /**
     * Aperçu dans le portail : PDF et images servis « en ligne », textes en text/plain (jamais interprétés).
     * Les autres formats (Office, ZIP) n'ont pas d'aperçu : 415, le client propose le téléchargement.
     */
    public function apercu(Request $request, Document $document)
    {
        abort_unless($this->peutConsulter($request->user(), $document), 403);
        abort_if($document->apercu === null, 415, 'Aperçu indisponible pour ce format : téléchargez le document.');

        $disque = Storage::disk('local');
        abort_unless($disque->exists($document->chemin), 404);
        $type = $document->apercu === 'texte' ? 'text/plain; charset=UTF-8' : $document->mime;

        return $disque->response($document->chemin, $document->nom_original, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function peutConsulter(User $user, Document $document): bool
    {
        if (! $user->peutVoirPromotion(Promotion::findOrFail($document->promotion_id))) {
            return false;
        }

        return ! ($user->hasRole(Role::ETUDIANT) && $document->espace === Document::RENDUS && $document->auteur_id !== $user->id);
    }

    private function peutSupprimer(User $user, Document $document): bool
    {
        // Un rendu déjà corrigé reste en place (la note en dépend), sauf pour l'administration
        return ($document->auteur_id === $user->id && $document->corrige_at === null) || $user->hasRole(Role::ADMIN);
    }
}
