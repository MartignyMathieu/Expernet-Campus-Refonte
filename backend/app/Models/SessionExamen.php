<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionExamen extends Model
{
    protected $table = 'sessions_examen';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['debut' => 'datetime', 'fin' => 'datetime'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function jury(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jury_membres')->withPivot('fonction');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(EvaluationCompetence::class);
    }
}
