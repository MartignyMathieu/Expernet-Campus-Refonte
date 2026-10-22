<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Module extends Model
{
    protected $guarded = ['id'];

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }
}
