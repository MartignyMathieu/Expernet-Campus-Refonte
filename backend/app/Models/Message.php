<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $guarded = ['id'];

    /** Délai pendant lequel l'auteur peut corriger son message. */
    public const DELAI_MODIFICATION_HEURES = 24;

    protected function casts(): array
    {
        return ['modifie_at' => 'datetime', 'supprime_at' => 'datetime'];
    }

    public function estSupprime(): bool
    {
        return $this->supprime_at !== null;
    }

    public function peutEtreModifiePar(User $user): bool
    {
        return ! $this->estSupprime() && $this->auteur_id === $user->id && $this->contenu !== ''
            && $this->created_at->gt(now()->subHours(self::DELAI_MODIFICATION_HEURES));
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(PieceJointe::class);
    }
}
