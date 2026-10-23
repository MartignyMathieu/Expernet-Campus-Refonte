<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Création du premier compte administrateur en production (aucun compte de démonstration n'y est créé).
 * Le mot de passe est demandé de façon masquée, jamais passé en argument (il resterait dans l'historique du shell).
 * Docker : docker compose -f docker-compose.prod.yml --env-file .env.production exec backend php artisan expernet:creer-admin admin@exemple.re
 */
class CreerAdministrateur extends Command
{
    protected $signature = 'expernet:creer-admin {email : Adresse du compte} {--prenom=} {--nom=}';

    protected $description = 'Crée un compte administrateur (mot de passe saisi de façon masquée)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $prenom = $this->option('prenom') ?: $this->ask('Prénom');
        $nom = $this->option('nom') ?: $this->ask('Nom');
        $motDePasse = $this->secret('Mot de passe (12 caractères minimum, majuscules, minuscules et chiffres)');

        $validation = Validator::make(compact('email', 'prenom', 'nom') + ['password' => $motDePasse], [
            'email' => ['required', 'email:rfc', 'unique:users,email'],
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $erreur) {
                $this->error($erreur);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'prenom' => $prenom, 'nom' => $nom, 'email' => $email, 'password' => $motDePasse,
            'role_id' => Role::where('code', Role::ADMIN)->value('id'),
        ]);
        Journal::ecrire('compte.creation', $user, "Création du compte administrateur {$email} (ligne de commande)");
        $this->info("Compte administrateur créé pour {$user->nomComplet()}. La double authentification sera demandée à la première connexion.");

        return self::SUCCESS;
    }
}
