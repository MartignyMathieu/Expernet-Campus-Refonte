<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\Document;
use App\Models\Emargement;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Administration : utilisateurs, séances, indicateurs globaux. */
class AdminController extends Controller
{
    public function tableauDeBord()
    {
        return response()->json([
            'utilisateurs' => User::count(),
            'formations' => Formation::count(),
            'promotions' => Promotion::count(),
            'candidatures_a_traiter' => Candidature::where('statut', 'recue')->count(),
            'seances_semaine' => Seance::whereDate('date', '>=', now()->startOfWeek())->whereDate('date', '<=', now()->endOfWeek())->count(),
            'absences_semaine' => Emargement::where('statut', Emargement::ABSENT)
                ->whereHas('seance', fn ($q) => $q->whereDate('date', '>=', now()->startOfWeek())->whereDate('date', '<=', now()->endOfWeek()))->count(),
            'documents_semaine' => Document::where('espace', '!=', Document::RENDUS)->where('created_at', '>=', now()->startOfWeek())->count(),
            'rendus_semaine' => Document::where('espace', Document::RENDUS)->where('created_at', '>=', now()->startOfWeek())->count(),
            'comptes_sans_mfa' => User::whereHas('role', fn ($q) => $q->whereIn('code', config('expernet.mfa_obligatoire_pour')))
                ->whereNull('two_factor_confirmed_at')->count(),
        ]);
    }

    public function utilisateurs()
    {
        return User::with('role:id,code,libelle')->orderBy('nom')->paginate(25)->through(fn (User $u) => [
            'id' => $u->id, 'prenom' => $u->prenom, 'nom' => $u->nom, 'email' => $u->email,
            'role' => $u->role->code, 'actif' => $u->actif, 'mfa' => $u->aMfaActive(), 'avatar_url' => $u->avatarUrl(),
        ]);
    }

    public function creerUtilisateur(Request $request)
    {
        $donnees = $request->validate([
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([Role::ADMIN, Role::FORMATEUR, Role::ETUDIANT])],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
        ]);

        $user = User::create([
            'prenom' => $donnees['prenom'], 'nom' => $donnees['nom'], 'email' => $donnees['email'],
            'password' => $donnees['password'], 'role_id' => Role::where('code', $donnees['role'])->value('id'),
        ]);

        return response()->json(['id' => $user->id], 201);
    }

    public function modifierUtilisateur(Request $request, User $user)
    {
        $donnees = $request->validate([
            'role' => ['sometimes', Rule::in([Role::ADMIN, Role::FORMATEUR, Role::ETUDIANT])],
            'actif' => ['sometimes', 'boolean'],
        ]);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas modifier votre propre compte.');

        if (isset($donnees['role'])) {
            $user->role_id = Role::where('code', $donnees['role'])->value('id');
        }
        if (array_key_exists('actif', $donnees)) {
            $user->actif = $donnees['actif'];
        }
        $user->save();

        return response()->json(['message' => 'Compte mis à jour.']);
    }

    /**
     * Réinitialise la double authentification d'un compte (téléphone perdu, clé serveur changée).
     * L'utilisateur devra la réactiver à sa prochaine connexion si son rôle l'exige.
     */
    public function reinitialiserMfa(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas réinitialiser votre propre double authentification ici.');

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return response()->json(['message' => 'Double authentification réinitialisée : elle sera redemandée à la prochaine connexion.']);
    }

    public function creerSeance(Request $request)
    {
        $donnees = $request->validate([
            'promotion_id' => ['required', 'exists:promotions,id'],
            'module_id' => ['required', 'exists:modules,id'],
            'formateur_id' => ['required', Rule::exists('users', 'id')->where('role_id', Role::where('code', Role::FORMATEUR)->value('id'))],
            'date' => ['required', 'date'],
            'creneau' => ['required', 'in:matin,apres_midi'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'lieu' => ['nullable', 'string', 'max:120'],
        ]);

        $existe = Seance::where('promotion_id', $donnees['promotion_id'])->whereDate('date', $donnees['date'])
            ->where('creneau', $donnees['creneau'])->exists();
        abort_if($existe, 422, 'Une séance existe déjà pour cette promotion sur ce créneau.');

        return response()->json(Seance::create($donnees), 201);
    }
}
