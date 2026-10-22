<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceJointe extends Model
{
    protected $table = 'message_pieces_jointes';

    protected $guarded = ['id'];

    protected $hidden = ['chemin'];

    /** Types affichables directement dans le navigateur (aperçu) ; les autres sont toujours téléchargés. */
    public const APERCU = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function estImage(): bool
    {
        return str_starts_with($this->mime, 'image/') && in_array($this->mime, self::APERCU, true);
    }
}
