<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Authentification : seuls les comptes actifs peuvent se connecter.
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->string('email'))->first();

            return $user && $user->actif && Hash::check($request->string('password'), $user->password) ? $user : null;
        });

        // Anti brute-force
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id')));
        RateLimiter::for('public-forms', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('messages', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('signature', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
    }
}
