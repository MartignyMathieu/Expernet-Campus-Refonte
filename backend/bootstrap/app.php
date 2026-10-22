<?php

use App\Http\Middleware\EnsureMfaEnrolled;
use App\Http\Middleware\EnsurePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Le front Nuxt est servi sur la même origine (Nginx) : authentification par cookie de session + CSRF.
        $middleware->statefulApi();
        $middleware->trustProxies(at: '*');
        // Lien d'API ouvert sans session (téléchargement, export .ics) : réponse 401, pas de redirection vers une route inexistante.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/connexion');
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'mfa' => EnsureMfaEnrolled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Secret MFA chiffré avec une autre APP_KEY (projet déplacé, clé régénérée) : message exploitable plutôt qu'une erreur 500.
        $exceptions->render(function (DecryptException $e, Request $request) {
            if ($request->is('api/auth/two-factor-challenge') || $request->is('api/auth/user/*')) {
                return response()->json([
                    'code' => 'MFA_ILLISIBLE',
                    'message' => 'Votre double authentification ne peut plus être vérifiée (clé de chiffrement du serveur modifiée). '
                        .'Demandez à l\'administration de la réinitialiser, puis réactivez-la.',
                ], 409);
            }
        });
    })->create();
