<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emargement extends Model
{
    public const PRESENT = 'present';
    public const ABSENT = 'absent';
    public const EXCUSE = 'excuse';

    protected $guarded = ['id'];

    protected $hidden = ['signature_path', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['signe_at' => 'datetime', 'justifie_at' => 'datetime'];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function justificatif(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'justificatif_id');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
