<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Secours en ligne de commande : réinitialise la double authentification d'un compte
 * (y compris un administrateur, qui ne peut pas le faire depuis l'interface pour lui-même).
 * Docker : docker compose exec backend php artisan mfa:reinitialiser formateur@expernet.test
 */
class ReinitialiserMfa extends Command
{
    protected $signature = 'mfa:reinitialiser {email : Adresse du compte} {--force : Ne pas demander de confirmation}';

    protected $description = 'Réinitialise la double authentification (TOTP) d\'un compte';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Aucun compte avec cette adresse.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Réinitialiser la double authentification de {$user->nomComplet()} ?", true)) {
            return self::FAILURE;
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->info("Double authentification réinitialisée pour {$user->email} : elle sera redemandée à la prochaine connexion.");

        return self::SUCCESS;
    }
}
