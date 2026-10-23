<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Page de santé pour la supervision et les sondes Docker : base de données, stockage des fichiers, cache.
 * 200 si tout va bien, 503 si un composant indispensable est en panne. Aucune information sensible exposée.
 */
class SanteController extends Controller
{
    public function __invoke()
    {
        $verifier = function (callable $test): array {
            $debut = microtime(true);
            try {
                $test();

                return ['ok' => true, 'ms' => (int) round((microtime(true) - $debut) * 1000)];
            } catch (\Throwable) {
                return ['ok' => false, 'ms' => (int) round((microtime(true) - $debut) * 1000)];
            }
        };

        $verifications = [
            'base_de_donnees' => $verifier(fn () => DB::select('select 1')),
            'stockage' => $verifier(function () {
                $fichier = 'sante/'.Str::random(8);
                Storage::disk('local')->put($fichier, 'ok');
                Storage::disk('local')->delete($fichier);
            }),
            'cache' => $verifier(function () {
                Cache::put('sante', 'ok', 10);
                throw_unless(Cache::get('sante') === 'ok', \RuntimeException::class);
            }),
        ];
        $ok = collect($verifications)->every(fn ($v) => $v['ok']);

        return response()->json([
            'statut' => $ok ? 'ok' : 'degrade',
            'application' => config('app.name'),
            'environnement' => app()->environment(),
            'heure' => now()->toIso8601String(),
            'verifications' => $verifications,
        ], $ok ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
