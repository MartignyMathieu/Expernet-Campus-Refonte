<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seance extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_emargement_hash'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'emargement_ouvert_at' => 'datetime',
            'emargement_expire_at' => 'datetime',
            'cloturee_at' => 'datetime',
            'travail_pour' => 'date:Y-m-d',
            'cahier_maj_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function formateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function emargements(): HasMany
    {
        return $this->hasMany(Emargement::class);
    }

    public function fenetreOuverte(): bool
    {
        return $this->emargement_ouvert_at !== null
            && $this->cloturee_at === null
            && now()->lessThanOrEqualTo($this->emargement_expire_at);
    }
}
