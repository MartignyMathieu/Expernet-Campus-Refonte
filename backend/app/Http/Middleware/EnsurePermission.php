<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC : refuse l'accès si le rôle de l'utilisateur ne possède pas la permission demandée.
 * Usage dans les routes : ->middleware('permission:emargement.sign')
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->actif) {
            abort(401);
        }

        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                abort(403, 'Permission requise : '.$permission);
            }
        }

        return $next($request);
    }
}
