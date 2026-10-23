<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Devoir donné à une promotion : consignes, échéance ; les rendus sont des documents de l'espace « rendus ». */
class Devoir extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['echeance' => 'datetime', 'coefficient' => 'float'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function rendus(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
