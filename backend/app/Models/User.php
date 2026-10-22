<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $attributes = ['actif' => true];

    protected $fillable = ['role_id', 'prenom', 'nom', 'email', 'password', 'actif'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'inscriptions')->withPivot('date_inscription')->withTimestamps();
    }

    public function seancesAnimees(): HasMany
    {
        return $this->hasMany(Seance::class, 'formateur_id');
    }

    public function emargements(): HasMany
    {
        return $this->hasMany(Emargement::class);
    }

    public function hasRole(string ...$codes): bool
    {
        return in_array($this->role?->code, $codes, true);
    }

    /** Vérifie une permission via le rôle (RBAC). Résultat mis en cache pour la requête. */
    public function hasPermission(string $code): bool
    {
        return $this->role !== null
            && $this->role->relationLoaded('permissions')
                ? $this->role->permissions->contains('code', $code)
                : (bool) $this->role?->permissions()->where('code', $code)->exists();
    }

    /** Périmètre de données : qui peut consulter une promotion ? */
    public function peutVoirPromotion(Promotion $promotion): bool
    {
        return match ($this->role?->code) {
            Role::ADMIN => true,
            Role::FORMATEUR => $promotion->seances()->where('formateur_id', $this->id)->exists(),
            Role::ETUDIANT => $promotion->etudiants()->whereKey($this->id)->exists(),
            default => false,
        };
    }

    public function aMfaActive(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }
}
