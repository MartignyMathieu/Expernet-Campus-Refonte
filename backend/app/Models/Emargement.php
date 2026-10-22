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
        return ['signe_at' => 'datetime'];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
