<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    public const CATEGORIES = ['conseils' => 'Conseils', 'vie-du-campus' => 'Vie du campus', 'evenements' => 'Événements', 'metiers' => 'Métiers du numérique'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['publie_le' => 'date:Y-m-d'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
