<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationCompetence extends Model
{
    public const NIVEAUX = ['non_acquis', 'en_cours', 'acquis', 'maitrise'];

    protected $table = 'evaluations_competence';
    protected $guarded = ['id'];

    public function competence(): BelongsTo
    {
        return $this->belongsTo(Competence::class);
    }
}
