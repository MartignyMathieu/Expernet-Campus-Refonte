<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Role;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('role.permissions');

        return response()->json([
            'id' => $user->id,
            'prenom' => $user->prenom,
            'nom' => $user->nom,
            'email' => $user->email,
            'avatar_url' => $user->avatarUrl(),
            'telephone' => $user->telephone,
            'bio' => $user->bio,
            'membre_depuis' => $user->created_at?->toDateString(),
            'role' => $user->role->code,
            'permissions' => $user->role->permissions->pluck('code'),
            'promotions' => match ($user->role->code) {
                Role::ETUDIANT => $user->promotions()->get(['promotions.id', 'promotions.libelle'])->map->only(['id', 'libelle'])->values(),
                Role::FORMATEUR => Promotion::whereHas('seances', fn ($q) => $q->where('formateur_id', $user->id))->get(['id', 'libelle'])->map->only(['id', 'libelle'])->values(),
                default => Promotion::get(['id', 'libelle'])->map->only(['id', 'libelle'])->values(),
            },
            'mfa' => [
                'active' => $user->aMfaActive(),
                'obligatoire' => $user->hasRole(...config('expernet.mfa_obligatoire_pour')),
            ],
        ]);
    }

    /**
     * Désactivation de sa propre double authentification (changement de téléphone, démonstration).
     * Le mot de passe actuel est exigé : une session laissée ouverte ne suffit pas.
     * Pour les rôles où elle est obligatoire, l'espace reste bloqué jusqu'à la réactivation.
     */
    public function desactiverMfa(Request $request)
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']], [
            'password.current_password' => 'Mot de passe incorrect.',
        ]);
        $user = $request->user();
        abort_unless($user->aMfaActive() || $user->two_factor_secret !== null, 422, 'La double authentification n\'est pas active.');

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $obligatoire = $user->hasRole(...config('expernet.mfa_obligatoire_pour'));

        return response()->json([
            'message' => $obligatoire
                ? 'Double authentification désactivée. Elle est obligatoire pour votre rôle : réactivez-la pour accéder à votre espace.'
                : 'Double authentification désactivée.',
            'obligatoire' => $obligatoire,
        ]);
    }
}
