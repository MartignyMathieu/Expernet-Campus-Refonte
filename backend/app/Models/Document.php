<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['chemin'];

    protected $appends = ['apercu'];

    /** Formats affichables dans le portail sans téléchargement. */
    public const APERCU_EN_LIGNE = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public const APERCU_TEXTE = ['text/plain', 'text/markdown', 'text/x-markdown', 'text/csv'];

    /** pdf | image | texte | null (aperçu indisponible : documents Office, archives…) */
    public function getApercuAttribute(): ?string
    {
        return match (true) {
            $this->mime === 'application/pdf' => 'pdf',
            in_array($this->mime, self::APERCU_EN_LIGNE, true) => 'image',
            in_array($this->mime, self::APERCU_TEXTE, true) => 'texte',
            default => null,
        };
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
