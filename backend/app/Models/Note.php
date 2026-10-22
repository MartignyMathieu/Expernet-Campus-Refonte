<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valeur' => 'decimal:2', 'coefficient' => 'decimal:2', 'date_evaluation' => 'date'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }
}
