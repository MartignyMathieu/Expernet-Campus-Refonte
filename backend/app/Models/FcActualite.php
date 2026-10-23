<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcActualite extends Model
{
    protected $table = 'fc_actualites';

    protected $guarded = ['id'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['publie_le' => 'date:Y-m-d'];
    }
}
