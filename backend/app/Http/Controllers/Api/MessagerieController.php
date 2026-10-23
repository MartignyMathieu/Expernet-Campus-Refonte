<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PieceJointe;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\MessagerieService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Messagerie de l'ENT : conversations privées, groupes, messages et non-lus. */
class MessagerieController extends Controller
{
    public function __construct(private readonly MessagerieService $service) {}

    /** Personnes que l'utilisateur a le droit de contacter. */
    public function contacts(Request $request)
    {
        return response()->json($this->service->contactsAutorises($request->user())->map(fn (User $u) => [
            'id' => $u->id, 'nom' => $u->nomComplet(), 'role' => $u->role->code, 'avatar_url' => $u->avatarUrl(),
        ])->values());
    }

    public function conversations(Request $request)
    {
        $user = $request->user();
        $conversations = $user->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['role', 'dernier_lu_id'])
            ->with(['participants:id,prenom,nom,role_id,avatar_path', 'participants.role:id,code', 'dernierMessage.auteur:id,prenom'])
            ->orderByDesc('dernier_message_at')->orderByDesc('conversations.id')
            ->get();

        return response()->json($conversations->map(fn (Conversation $c) => $this->resume($c, $user))->values());
    }

    /** Ouvre (ou retrouve) une conversation privée avec un contact autorisé. */
    public function ouvrirPrivee(Request $request)
    {
        $donnees = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = $request->user();
        $destinataire = User::findOrFail($donnees['user_id']);

        abort_if($destinataire->id === $user->id, 422, 'Vous ne pouvez pas vous écrire à vous-même.');
        abort_unless($this->service->peutContacter($user, $destinataire), 403, 'Ce contact ne fait pas partie de votre périmètre.');

        $conversation = $this->service->conversationPrivee($user, $destinataire);

        return response()->json($this->resume($conversation->load('participants.role'), $user), $conversation->wasRecentlyCreated ? 201 : 200);
    }

    /** Création d'un groupe (formateur ou admin). */
    public function creerGroupe(Request $request)
    {
        $user = $request->user();
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:100'],
            'membres' => ['array', 'max:200'],
            'membres.*' => ['integer', 'distinct', 'exists:users,id'],
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
        ]);

        $autorises = $this->service->contactsAutorises($user)->pluck('id');
        $horsPerimetre = collect($donnees['membres'] ?? [])->diff($autorises);
        abort_if($horsPerimetre->isNotEmpty(), 403, 'Certains membres ne font pas partie de votre périmètre.');

        $promotion = isset($donnees['promotion_id']) ? Promotion::findOrFail($donnees['promotion_id']) : null;
        abort_if($promotion && ! $user->peutVoirPromotion($promotion), 403, 'Vous n\'intervenez pas dans cette promotion.');
        abort_if(empty($donnees['membres']) && ! $promotion, 422, 'Ajoutez au moins un membre ou une promotion.');

        $groupe = $this->service->creerGroupe($user, trim($donnees['nom']), $donnees['membres'] ?? [], $promotion);

        return response()->json($this->resume($groupe->load('participants.role'), $user), 201);
    }

    /** Ajout / retrait de membres par un gestionnaire du groupe. */
    public function modifierMembres(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->type === Conversation::GROUPE && $conversation->estGeree($user), 403, 'Réservé aux gestionnaires du groupe.');

        $donnees = $request->validate([
            'ajouter' => ['array'], 'ajouter.*' => ['integer', 'exists:users,id'],
            'retirer' => ['array'], 'retirer.*' => ['integer', 'exists:users,id'],
        ]);

        $autorises = $this->service->contactsAutorises($user)->pluck('id');
        $ajouts = collect($donnees['ajouter'] ?? []);
        abort_if($ajouts->diff($autorises)->isNotEmpty(), 403, 'Certains membres ne font pas partie de votre périmètre.');
        abort_if(in_array($user->id, $donnees['retirer'] ?? [], true), 422, 'Le gestionnaire ne peut pas se retirer lui-même.');

        $conversation->participants()->syncWithoutDetaching($ajouts->mapWithKeys(fn ($id) => [$id => ['role' => 'membre']]));
        $conversation->participants()->detach($donnees['retirer'] ?? []);

        return response()->json($this->resume($conversation->fresh()->load('participants.role'), $user));
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->aParticipant($user), 403);
        $apres = (int) $request->query('apres', 0);
        // Synchronisation : messages modifiés ou supprimés depuis la dernière interrogation
        $majDepuis = $request->date('maj_depuis');

        $messages = $conversation->messages()->with(['auteur:id,prenom,nom,avatar_path', 'piecesJointes'])
            ->when($apres > 0, fn ($q) => $q->where(fn ($w) => $w->where('id', '>', $apres)
                ->when($majDepuis, fn ($o) => $o->orWhere('updated_at', '>', $majDepuis))))
            ->orderByDesc('id')->limit(100)->get()->reverse()->values();

        $dernier = $messages->max('id');
        if ($dernier && $dernier > $apres) {
            $this->service->marquerLu($conversation, $user, $dernier);
        }
        $gestionnaire = $conversation->type === Conversation::GROUPE && $conversation->estGeree($user);
        $messages->each->setRelation('conversation', $conversation);

        return response()->json($messages->map(fn (Message $m) => $this->formatMessage($m, $user, $gestionnaire)));
    }

    public function envoyer(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->aParticipant($user), 403);
        $donnees = $request->validate([
            'contenu' => ['nullable', 'string', 'max:2000'],
            'fichiers' => ['array', 'max:5'],
            'fichiers.*' => ['file', 'max:10240', 'mimes:pdf,docx,pptx,xlsx,odt,ods,zip,png,jpg,jpeg,gif,webp,txt,md'],
        ], [
            'fichiers.max' => '5 fichiers au maximum par message.',
            'fichiers.*.max' => 'Chaque fichier doit faire moins de 10 Mo.',
            'fichiers.*.mimes' => 'Type de fichier non autorisé (PDF, Office, images, texte ou ZIP).',
        ]);
        $contenu = trim(strip_tags($donnees['contenu'] ?? ''));
        $fichiers = $request->file('fichiers', []);
        abort_if($contenu === '' && ! $fichiers, 422, 'Le message est vide.');

        $message = $this->service->envoyer($conversation, $user, $contenu, $fichiers);

        return response()->json($this->formatMessage($message->load(['auteur:id,prenom,nom,avatar_path', 'piecesJointes']), $user), 201);
    }

    /** Correction d'un message par son auteur (24 h après l'envoi au plus). */
    public function modifier(Request $request, Message $message)
    {
        $user = $request->user();
        abort_unless($message->conversation->aParticipant($user), 403);
        abort_unless($message->auteur_id === $user->id, 403, 'Seul l\'auteur peut modifier ce message.');
        abort_unless($message->peutEtreModifiePar($user), 422, 'Ce message ne peut plus être modifié.');

        $donnees = $request->validate(['contenu' => ['required', 'string', 'max:2000']]);
        $contenu = trim(strip_tags($donnees['contenu']));
        abort_if($contenu === '', 422, 'Le message est vide.');

        $this->service->modifier($message, $contenu);

        return response()->json($this->formatMessage($message->load(['auteur:id,prenom,nom,avatar_path', 'piecesJointes']), $user));
    }

    /** Suppression par l'auteur, ou par un gestionnaire du groupe (modération). */
    public function supprimer(Request $request, Message $message)
    {
        $user = $request->user();
        $conversation = $message->conversation;
        abort_unless($conversation->aParticipant($user), 403);
        $moderation = $conversation->type === Conversation::GROUPE && $conversation->estGeree($user);
        abort_unless($message->auteur_id === $user->id || $moderation, 403, 'Vous ne pouvez supprimer que vos propres messages.');
        abort_if($message->estSupprime(), 422, 'Ce message est déjà supprimé.');

        $this->service->supprimer($message);

        return response()->json($this->formatMessage($message->load(['auteur:id,prenom,nom,avatar_path', 'piecesJointes']), $user));
    }

    /** Fichier joint : affiché dans le navigateur s'il s'agit d'une image ou d'un PDF, sinon téléchargé. */
    public function pieceJointe(Request $request, PieceJointe $pieceJointe)
    {
        abort_unless($pieceJointe->message->conversation->aParticipant($request->user()), 403);

        $disque = Storage::disk('local');
        abort_unless($disque->exists($pieceJointe->chemin), 404);
        $enLigne = in_array($pieceJointe->mime, PieceJointe::APERCU, true) && ! $request->boolean('telechargement');

        return $enLigne
            ? $disque->response($pieceJointe->chemin, $pieceJointe->nom_original, [
                'Content-Type' => $pieceJointe->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600',
            ])
            : $disque->download($pieceJointe->chemin, $pieceJointe->nom_original, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function nonLus(Request $request)
    {
        return response()->json(['non_lus' => $this->service->totalNonLus($request->user())]);
    }

    private function resume(Conversation $c, User $user): array
    {
        $autres = $c->participants->where('id', '!=', $user->id);
        $moi = $c->participants->firstWhere('id', $user->id);

        return [
            'id' => $c->id,
            'type' => $c->type,
            'titre' => $c->type === Conversation::GROUPE ? $c->nom : $autres->first()?->nomComplet(),
            'interlocuteur_role' => $c->type === Conversation::PRIVEE ? $autres->first()?->role?->code : null,
            'avatar_url' => $c->type === Conversation::PRIVEE ? $autres->first()?->avatarUrl() : null,
            'membres' => $c->participants->map(fn ($p) => ['id' => $p->id, 'nom' => $p->nomComplet(), 'avatar_url' => $p->avatarUrl(), 'gestionnaire' => $p->pivot->role === 'gestionnaire'])->values(),
            'gestionnaire' => $moi?->pivot?->role === 'gestionnaire',
            'dernier_message' => $c->relationLoaded('dernierMessage') && $c->dernierMessage ? [
                'extrait' => MessagerieService::extrait($c->dernierMessage),
                'auteur' => $c->dernierMessage->auteur_id === $user->id ? 'Vous' : $c->dernierMessage->auteur?->prenom,
                'date' => $c->dernierMessage->created_at->toIso8601String(),
            ] : null,
            'non_lus' => $this->service->nonLus($c, $user, $moi?->pivot?->dernier_lu_id),
        ];
    }

    private function formatMessage(Message $m, User $user, ?bool $gestionnaire = null): array
    {
        $gestionnaire ??= $m->auteur_id !== $user->id && $m->conversation->type === Conversation::GROUPE && $m->conversation->estGeree($user);

        return [
            'id' => $m->id,
            'contenu' => $m->contenu,
            'auteur' => ['id' => $m->auteur_id, 'nom' => $m->auteur?->nomComplet(), 'avatar_url' => $m->auteur?->avatarUrl()],
            'moi' => $m->auteur_id === $user->id,
            'date' => $m->created_at->toIso8601String(),
            'maj' => $m->updated_at->toIso8601String(),
            'modifie' => $m->modifie_at !== null,
            'supprime' => $m->estSupprime(),
            'peut_modifier' => $m->peutEtreModifiePar($user),
            'peut_supprimer' => ! $m->estSupprime() && ($m->auteur_id === $user->id || $gestionnaire),
            'pieces_jointes' => $m->piecesJointes->map(fn (PieceJointe $p) => [
                'id' => $p->id, 'nom' => $p->nom_original, 'mime' => $p->mime, 'taille' => $p->taille, 'image' => $p->estImage(),
                'url' => '/api/messagerie/pieces-jointes/'.$p->id,
            ])->values(),
        ];
    }
}
