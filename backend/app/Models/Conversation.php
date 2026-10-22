<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    public const PRIVEE = 'privee';
    public const GROUPE = 'groupe';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dernier_message_at' => 'datetime'];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withPivot(['role', 'dernier_lu_id'])->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function dernierMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function aParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    public function estGeree(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->wherePivot('role', 'gestionnaire')->exists();
    }

    public static function clePrivee(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }
}
