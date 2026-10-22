<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidature extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['consentement_rgpd_at' => 'datetime'];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }
}
