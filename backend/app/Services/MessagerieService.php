<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\PieceJointe;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Règles de la messagerie :
 * - un étudiant écrit aux étudiants de sa promotion, à ses formateurs et à l'administration ;
 * - un formateur écrit aux étudiants des promotions où il enseigne, aux autres formateurs et à l'administration ;
 * - l'administration écrit à tout le monde ;
 * - seuls les formateurs et les administrateurs créent des groupes ; le créateur en est gestionnaire.
 */
class MessagerieService
{
    /** @return Collection<int, User> */
    public function contactsAutorises(User $user): Collection
    {
        $base = User::query()->where('actif', true)->whereKeyNot($user->id)->with('role:id,code');

        $ids = match ($user->role->code) {
            Role::ADMIN => null,
            Role::FORMATEUR => $this->idsEtudiantsDe($this->promotionsEnseignees($user))
                ->merge($this->idsParRole([Role::FORMATEUR, Role::ADMIN])),
            Role::ETUDIANT => $this->idsEtudiantsDe($user->promotions()->pluck('promotions.id'))
                ->merge(DB::table('seances')->whereIn('promotion_id', $user->promotions()->pluck('promotions.id'))->distinct()->pluck('formateur_id'))
                ->merge($this->idsParRole([Role::ADMIN])),
            default => collect(),
        };

        return ($ids === null ? $base : $base->whereIn('id', $ids->unique()))->orderBy('nom')->orderBy('prenom')->get();
    }

    public function peutContacter(User $de, User $vers): bool
    {
        return $this->contactsAutorises($de)->contains('id', $vers->id);
    }

    /** Retrouve ou crée l'unique conversation privée entre deux personnes. */
    public function conversationPrivee(User $de, User $vers): Conversation
    {
        $cle = Conversation::clePrivee($de->id, $vers->id);

        return DB::transaction(function () use ($cle, $de, $vers) {
            $conversation = Conversation::firstOrCreate(['cle_privee' => $cle], ['type' => Conversation::PRIVEE, 'createur_id' => $de->id]);
            if ($conversation->wasRecentlyCreated) {
                $conversation->participants()->attach([$de->id => ['role' => 'membre'], $vers->id => ['role' => 'membre']]);
            }

            return $conversation;
        });
    }

    /** @param  array<int>  $membres */
    public function creerGroupe(User $createur, string $nom, array $membres, ?Promotion $promotion = null): Conversation
    {
        return DB::transaction(function () use ($createur, $nom, $membres, $promotion) {
            $groupe = Conversation::create([
                'type' => Conversation::GROUPE, 'nom' => $nom, 'createur_id' => $createur->id, 'promotion_id' => $promotion?->id,
            ]);

            $ids = collect($membres);
            if ($promotion) {
                $ids = $ids->merge($promotion->etudiants()->pluck('users.id'));
            }

            $groupe->participants()->attach($createur->id, ['role' => 'gestionnaire']);
            $groupe->participants()->attach($ids->unique()->reject(fn ($id) => $id === $createur->id)->mapWithKeys(fn ($id) => [$id => ['role' => 'membre']]));

            Message::create(['conversation_id' => $groupe->id, 'auteur_id' => $createur->id, 'contenu' => $createur->prenom.' a créé le groupe « '.$nom.' ».']);
            $groupe->forceFill(['dernier_message_at' => now()])->save();

            return $groupe;
        });
    }

    /** @param  array<UploadedFile>  $fichiers */
    public function envoyer(Conversation $conversation, User $auteur, string $contenu, array $fichiers = []): Message
    {
        $chemins = [];
        try {
            $message = DB::transaction(function () use ($conversation, $auteur, $contenu, $fichiers, &$chemins) {
                $message = Message::create(['conversation_id' => $conversation->id, 'auteur_id' => $auteur->id, 'contenu' => $contenu]);
                foreach ($fichiers as $fichier) {
                    $chemins[] = $chemin = $fichier->store('messagerie/'.$conversation->id, 'local');
                    $message->piecesJointes()->create([
                        'chemin' => $chemin,
                        'nom_original' => mb_substr($fichier->getClientOriginalName(), 0, 255),
                        'mime' => $fichier->getMimeType() ?? 'application/octet-stream',
                        'taille' => $fichier->getSize(),
                    ]);
                }

                return $message;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($chemins); // pas de fichier orphelin si l'enregistrement échoue
            throw $e;
        }

        $conversation->forceFill(['dernier_message_at' => $message->created_at])->save();
        $this->marquerLu($conversation, $auteur, $message->id);

        return $message;
    }

    /** Extrait affiché dans la liste des conversations. */
    public static function extrait(Message $message): string
    {
        if ($message->contenu !== '') {
            return mb_strimwidth($message->contenu, 0, 80, '…');
        }
        $n = $message->piecesJointes()->count();

        return $n > 1 ? "📎 $n fichiers" : '📎 '.($message->piecesJointes()->value('nom_original') ?? 'Fichier');
    }

    public function marquerLu(Conversation $conversation, User $user, ?int $jusqua = null): void
    {
        $jusqua ??= (int) $conversation->messages()->max('id');
        $conversation->participants()->updateExistingPivot($user->id, ['dernier_lu_id' => $jusqua]);
    }

    public function nonLus(Conversation $conversation, User $user, ?int $dernierLu): int
    {
        return $conversation->messages()->where('auteur_id', '!=', $user->id)->where('id', '>', (int) $dernierLu)->count();
    }

    public function totalNonLus(User $user): int
    {
        return (int) DB::table('messages')
            ->join('conversation_participants as cp', function ($j) use ($user) {
                $j->on('cp.conversation_id', '=', 'messages.conversation_id')->where('cp.user_id', '=', $user->id);
            })
            ->where('messages.auteur_id', '!=', $user->id)
            ->whereRaw('messages.id > COALESCE(cp.dernier_lu_id, 0)')
            ->count();
    }

    private function promotionsEnseignees(User $formateur): Collection
    {
        return DB::table('seances')->where('formateur_id', $formateur->id)->distinct()->pluck('promotion_id');
    }

    private function idsEtudiantsDe(Collection $promotionIds): Collection
    {
        return DB::table('inscriptions')->whereIn('promotion_id', $promotionIds)->distinct()->pluck('user_id');
    }

    private function idsParRole(array $codes): Collection
    {
        return User::whereHas('role', fn ($q) => $q->whereIn('code', $codes))->pluck('id');
    }
}
