<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcStage extends Model
{
    protected $table = 'fc_stages';

    protected $guarded = ['id'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'public' => 'array', 'tarifs' => 'array', 'objectifs' => 'array', 'prerequis' => 'array', 'programme' => 'array',
            'moyens' => 'array', 'methodes' => 'array', 'evaluation' => 'array', 'prochaine_session' => 'date:Y-m-d',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function domaine(): BelongsTo
    {
        return $this->belongsTo(FcDomaine::class, 'fc_domaine_id');
    }
}
