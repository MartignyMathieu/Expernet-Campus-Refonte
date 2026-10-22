<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageContact extends Model
{
    protected $table = 'messages_contact';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['consentement_rgpd_at' => 'datetime'];
    }
}
