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
}
