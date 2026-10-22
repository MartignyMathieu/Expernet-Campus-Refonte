<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MFA obligatoire pour les comptes à privilèges (admin, formateur).
 * Tant que la double authentification n'est pas activée et confirmée,
 * l'API répond 403 avec le code MFA_REQUIRED : le front redirige vers l'enrôlement.
 */
class EnsureMfaEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole(...config('expernet.mfa_obligatoire_pour')) && ! $user->aMfaActive()) {
            return response()->json([
                'message' => 'La double authentification doit être activée pour ce compte.',
                'code' => 'MFA_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
