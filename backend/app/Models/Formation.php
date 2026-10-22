<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    public const TYPES_ELEMENT = ['objectif', 'prerequis', 'debouche', 'modalite'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['eligible_cpf' => 'boolean', 'publiee' => 'boolean', 'prochaine_rentree' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(FormationElement::class)->orderBy('ordre');
    }

    public function campus(): BelongsToMany
    {
        return $this->belongsToMany(Campus::class, 'formation_campus');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function competences(): HasMany
    {
        return $this->hasMany(Competence::class);
    }

    /** Libellé « Bac+N » déduit du niveau du cadre national (5 = Bac+2, 6 = Bac+3/4, 7 = Bac+5). */
    public function libelleNiveau(): string
    {
        return match ($this->niveau_rncp) {
            5 => 'Bac+2',
            6 => 'Bac+3/4',
            7 => 'Bac+5',
            default => 'Niveau '.$this->niveau_rncp,
        };
    }

    public function scopePubliee(Builder $q): Builder
    {
        return $q->where('publiee', true);
    }
}
