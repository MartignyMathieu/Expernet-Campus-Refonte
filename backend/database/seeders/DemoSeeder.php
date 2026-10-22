<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Competence;
use App\Models\Emargement;
use App\Models\Formation;
use App\Models\Module;
use App\Models\Note;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Seance;
use App\Models\SessionExamen;
use App\Models\User;
use App\Services\EmargementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Jeu de données de DÉMONSTRATION (personnes fictives, domaine .test).
 * Le mot de passe vient de DEMO_PASSWORD ; s'il est vide, un mot de passe aléatoire est généré et affiché.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $motDePasse = env('DEMO_PASSWORD') ?: Str::password(16, symbols: false);
        $role = fn (string $code) => Role::where('code', $code)->value('id');

        $admin = User::updateOrCreate(['email' => 'admin@expernet.test'], ['prenom' => 'Alice', 'nom' => 'Admin', 'password' => $motDePasse, 'role_id' => $role(Role::ADMIN)]);
        $f1 = User::updateOrCreate(['email' => 'formateur@expernet.test'], ['prenom' => 'Florent', 'nom' => 'Formateur', 'password' => $motDePasse, 'role_id' => $role(Role::FORMATEUR)]);
        $f2 = User::updateOrCreate(['email' => 'formatrice@expernet.test'], ['prenom' => 'Fanny', 'nom' => 'Formatrice', 'password' => $motDePasse, 'role_id' => $role(Role::FORMATEUR)]);

        $prenoms = ['Emma', 'Lucas', 'Inès', 'Noah', 'Léa', 'Hugo', 'Chloé', 'Tom'];
        $etudiants = collect($prenoms)->map(fn ($p, $i) => User::updateOrCreate(
            ['email' => $i === 0 ? 'etudiant@expernet.test' : Str::slug($p).'@expernet.test'],
            ['prenom' => $p, 'nom' => 'Démo', 'password' => $motDePasse, 'role_id' => $role(Role::ETUDIANT)],
        ));

        $cda = Formation::where('slug', 'cda-bac4')->firstOrFail();
        $promo = Promotion::updateOrCreate(['libelle' => 'CDA 2026-2028'], [
            'formation_id' => $cda->id, 'campus_id' => Campus::where('nom', 'like', 'La Réunion%')->value('id'),
            'date_debut' => '2026-10-19', 'date_fin' => '2028-07-31',
        ]);
        $promo->etudiants()->syncWithPivotValues($etudiants->pluck('id'), ['date_inscription' => '2026-10-19']);

        $modules = collect([
            ['ENV', 'Environnement de développement', 35], ['BDD', 'Conception et administration de bases de données', 70],
            ['WEB', 'Développement web back-end', 105], ['FRONT', 'Interfaces utilisateur', 70], ['PROJ', 'Conduite de projet', 35],
        ])->map(fn ($m) => Module::updateOrCreate(['formation_id' => $cda->id, 'code' => $m[0]], ['intitule' => $m[1], 'volume_horaire' => $m[2]]));

        // Compétences des 3 activités types du titre CDA (RNCP37873)
        foreach ([
            ['AT1', 'Développer une application sécurisée'],
            ['AT2', 'Concevoir et développer une application sécurisée organisée en couches'],
            ['AT3', 'Préparer le déploiement d\'une application sécurisée'],
        ] as [$code, $libelle]) {
            Competence::updateOrCreate(['formation_id' => $cda->id, 'code' => $code], ['libelle' => $libelle]);
        }

        // Séances : 2 semaines passées (clôturées, avec émargements), la semaine courante et la suivante.
        // Les séances du jour sont toujours attribuées à formateur@ pour la démonstration.
        $service = app(EmargementService::class);
        $debut = Carbon::today()->startOfWeek()->subWeeks(2);
        foreach (range(0, 27) as $j) {
            $jour = $debut->copy()->addDays($j);
            if ($jour->isWeekend()) {
                continue;
            }
            foreach (['matin' => ['08:30', '12:00'], 'apres_midi' => ['13:00', '16:30']] as $creneau => [$hd, $hf]) {
                $module = $modules[($j + ($creneau === 'matin' ? 0 : 1)) % $modules->count()];
                $seance = Seance::updateOrCreate(
                    ['promotion_id' => $promo->id, 'date' => $jour->toDateString(), 'creneau' => $creneau],
                    ['module_id' => $module->id, 'formateur_id' => ($jour->isToday() || $j % 2 === 0 ? $f1 : $f2)->id, 'heure_debut' => $hd, 'heure_fin' => $hf, 'lieu' => 'Salle Distanciel 1'],
                );

                if ($jour->lt(Carbon::today()) && $seance->cloturee_at === null) {
                    foreach ($etudiants as $k => $e) {
                        // Absentéisme simulé : « Tom » s'absente souvent, « Hugo » ponctuellement — pour démontrer les alertes.
                        $absent = ($k === 7 && $j % 3 !== 0) || ($k === 5 && $j % 5 === 0);
                        if (! $absent) {
                            $t = $jour->copy()->setTimeFromTimeString($hd)->addMinutes(3 + $k);
                            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
                            $chemin = sprintf('signatures/%d/%d_demo.png', $seance->id, $e->id);
                            Storage::disk('local')->put($chemin, $png);
                            Emargement::updateOrCreate(['seance_id' => $seance->id, 'user_id' => $e->id], [
                                'statut' => Emargement::PRESENT, 'signe_at' => $t, 'signature_path' => $chemin,
                                'empreinte' => EmargementService::calculerEmpreinte($seance->id, $e->id, $t->toIso8601String(), hash('sha256', $png)),
                            ]);
                        }
                    }
                    $service->cloturer($seance->load('promotion'), $seance->formateur);
                }
            }
        }

        // Notes
        foreach ($etudiants as $k => $e) {
            foreach ($modules->take(3) as $m => $module) {
                Note::updateOrCreate(['module_id' => $module->id, 'etudiant_id' => $e->id, 'intitule' => 'Évaluation '.$module->code], [
                    'formateur_id' => $f1->id, 'valeur' => 9 + (($k * 3 + $m * 5) % 11), 'coefficient' => 1,
                    'date_evaluation' => Carbon::today()->subDays(3 + $m),
                ]);
            }
        }

        // Session d'examen + jury
        $examen = SessionExamen::updateOrCreate(['promotion_id' => $promo->id, 'intitule' => 'Évaluation intermédiaire — AT1'], [
            'debut' => Carbon::today()->addWeeks(3)->setTime(8, 30), 'fin' => Carbon::today()->addWeeks(3)->setTime(12, 0), 'lieu' => 'Le Port — Salle 528',
        ]);
        $examen->jury()->sync([$f1->id => ['fonction' => 'president'], $f2->id => ['fonction' => 'membre']]);

        // Messagerie : un groupe de promotion et une conversation privée (une seule fois)
        if (! \App\Models\Conversation::exists()) {
            $messagerie = app(\App\Services\MessagerieService::class);
            $groupe = $messagerie->creerGroupe($f1, 'CDA 2026-2028 · Infos promo', [], $promo);
            $messagerie->envoyer($groupe, $f1, 'Bonjour à tous, le support du module back-end est disponible dans Documents. Pensez à émarger dès l\'ouverture de la séance.');
            $messagerie->envoyer($groupe, $etudiants[1], 'Merci ! Est-ce qu\'on aura le TP noté vendredi ?');
            $messagerie->envoyer($groupe, $f1, 'Oui, vendredi après-midi, en binôme.');
            $prive = $messagerie->conversationPrivee($etudiants[0], $f1);
            $messagerie->envoyer($prive, $etudiants[0], 'Bonjour, je serai en retard demain matin (rendez-vous médical), je vous transmets le justificatif.');
            $messagerie->envoyer($prive, $f1, 'Bien noté, merci de prévenir. Transmettez le justificatif à la scolarité.');
        }

        $this->command?->getOutput()->writeln('');
        $this->command?->warn('Comptes de démonstration : admin@ / formateur@ / formatrice@ / etudiant@expernet.test');
        if (! env('DEMO_PASSWORD')) {
            $this->command?->warn('Mot de passe généré (DEMO_PASSWORD vide) : '.$motDePasse);
        }
    }
}
