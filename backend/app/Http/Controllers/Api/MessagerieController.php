<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\MessagerieService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Messagerie de l'ENT : conversations privées, groupes, messages et non-lus. */
class MessagerieController extends Controller
{
    public function __construct(private readonly MessagerieService $service) {}

    /** Personnes que l'utilisateur a le droit de contacter. */
    public function contacts(Request $request)
    {
        return response()->json($this->service->contactsAutorises($request->user())->map(fn (User $u) => [
            'id' => $u->id, 'nom' => $u->nomComplet(), 'role' => $u->role->code,
        ])->values());
    }

    public function conversations(Request $request)
    {
        $user = $request->user();
        $conversations = $user->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['role', 'dernier_lu_id'])
            ->with(['participants:id,prenom,nom,role_id', 'participants.role:id,code', 'dernierMessage.auteur:id,prenom'])
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

        $messages = $conversation->messages()->with('auteur:id,prenom,nom')
            ->when($apres > 0, fn ($q) => $q->where('id', '>', $apres))
            ->orderByDesc('id')->limit(100)->get()->reverse()->values();

        if ($messages->isNotEmpty()) {
            $this->service->marquerLu($conversation, $user, $messages->last()->id);
        }

        return response()->json($messages->map(fn (Message $m) => $this->formatMessage($m, $user)));
    }

    public function envoyer(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->aParticipant($user), 403);
        $donnees = $request->validate(['contenu' => ['required', 'string', 'max:2000']]);
        $contenu = trim(strip_tags($donnees['contenu']));
        abort_if($contenu === '', 422, 'Le message est vide.');

        $message = $this->service->envoyer($conversation, $user, $contenu);

        return response()->json($this->formatMessage($message->load('auteur:id,prenom,nom'), $user), 201);
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
            'membres' => $c->participants->map(fn ($p) => ['id' => $p->id, 'nom' => $p->nomComplet(), 'gestionnaire' => $p->pivot->role === 'gestionnaire'])->values(),
            'gestionnaire' => $moi?->pivot?->role === 'gestionnaire',
            'dernier_message' => $c->relationLoaded('dernierMessage') && $c->dernierMessage ? [
                'extrait' => mb_strimwidth($c->dernierMessage->contenu, 0, 80, '…'),
                'auteur' => $c->dernierMessage->auteur_id === $user->id ? 'Vous' : $c->dernierMessage->auteur?->prenom,
                'date' => $c->dernierMessage->created_at->toIso8601String(),
            ] : null,
            'non_lus' => $this->service->nonLus($c, $user, $moi?->pivot?->dernier_lu_id),
        ];
    }

    private function formatMessage(Message $m, User $user): array
    {
        return [
            'id' => $m->id,
            'contenu' => $m->contenu,
            'auteur' => ['id' => $m->auteur_id, 'nom' => $m->auteur?->nomComplet()],
            'moi' => $m->auteur_id === $user->id,
            'date' => $m->created_at->toIso8601String(),
        ];
    }
}
