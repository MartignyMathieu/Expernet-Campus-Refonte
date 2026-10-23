<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcActualite extends Model
{
    protected $table = 'fc_actualites';

    protected $guarded = ['id'];

    protected $hidden = ['created_at', 'updated_at'];
}
