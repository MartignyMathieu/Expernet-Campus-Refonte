<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filiere extends Model
{
    protected $guarded = ['id'];

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }
}
