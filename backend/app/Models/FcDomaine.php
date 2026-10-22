<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FcDomaine extends Model
{
    protected $table = 'fc_domaines';
    protected $guarded = ['id'];

    public function stages(): HasMany
    {
        return $this->hasMany(FcStage::class)->orderBy('ordre');
    }
}
