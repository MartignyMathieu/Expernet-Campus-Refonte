<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Competence;
use App\Models\Document;
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

        // Documents de la promotion (aperçu dans le portail : PDF, image, texte)
        $this->documentsDemo($promo, $f1, $modules);

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

        // Pièce jointe de démonstration (ajoutée aussi sur une base déjà peuplée)
        $groupe = \App\Models\Conversation::where('nom', 'CDA 2026-2028 · Infos promo')->first();
        if ($groupe && ! \App\Models\PieceJointe::exists()) {
            $messagerie = app(\App\Services\MessagerieService::class);
            $consignes = $messagerie->envoyer($groupe, $f1, 'Les consignes sont en pièce jointe 📎 Bon courage à tous 💪');
            $chemin = 'messagerie/'.$groupe->id.'/consignes-tp-note.pdf';
            Storage::disk('local')->put($chemin, \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                '<html><head><meta charset="utf-8"><title>Consignes TP noté</title></head><body>'
                .'<h1 style="font-family:sans-serif;color:#0D5A8A">TP noté — API REST sécurisée</h1>'
                .'<p style="font-family:sans-serif">Promotion CDA 2026-2028 · vendredi après-midi · en binôme</p>'
                .'<ol style="font-family:sans-serif"><li>Exposer une ressource CRUD documentée (OpenAPI).</li><li>Protéger les routes (authentification, rôles).</li>'
                .'<li>Écrire au moins cinq tests automatisés.</li><li>Déposer le lien du dépôt Git dans la messagerie.</li></ol></body></html>'
            )->output());
            $consignes->piecesJointes()->create(['chemin' => $chemin, 'nom_original' => 'Consignes TP noté.pdf', 'mime' => 'application/pdf', 'taille' => Storage::disk('local')->size($chemin)]);
        }

        $this->command?->getOutput()->writeln('');
        $this->command?->warn('Comptes de démonstration : admin@ / formateur@ / formatrice@ / etudiant@expernet.test');
        if (! env('DEMO_PASSWORD')) {
            $this->command?->warn('Mot de passe généré (DEMO_PASSWORD vide) : '.$motDePasse);
        }
    }

    /** Supports de cours générés à la volée (PDF, schéma PNG, mémo texte) : une seule fois par promotion. */
    private function documentsDemo(Promotion $promo, User $auteur, $modules): void
    {
        if (Document::where('promotion_id', $promo->id)->exists()) {
            return;
        }
        $disque = Storage::disk('local');
        $ajouter = function (string $titre, string $nom, string $mime, string $contenu, ?Module $module) use ($disque, $promo, $auteur) {
            $chemin = 'documents/'.$promo->id.'/'.Str::uuid().'.'.pathinfo($nom, PATHINFO_EXTENSION);
            $disque->put($chemin, $contenu);
            Document::create([
                'promotion_id' => $promo->id, 'module_id' => $module?->id, 'auteur_id' => $auteur->id, 'titre' => $titre,
                'chemin' => $chemin, 'nom_original' => $nom, 'mime' => $mime, 'taille' => strlen($contenu),
            ]);
        };

        $css = 'font-family:Helvetica,sans-serif;color:#14202B'; // police standard PDF : fichier léger
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
            '<html><head><meta charset="utf-8"><title>Support — API REST sécurisée</title></head><body>'
            ."<div style=\"$css\"><p style=\"color:#009BDD;font-weight:bold;letter-spacing:2px\">EXPERNET CAMPUS · CDA 2026-2028</p>"
            .'<h1 style="color:#0D5A8A">Développement web back-end</h1><h2>Séance 3 — Concevoir une API REST sécurisée</h2>'
            .'<h3>1. Principes REST</h3><ul><li>Ressources identifiées par des URI, verbes HTTP explicites (GET, POST, PATCH, DELETE).</li><li>Codes de statut : 200, 201, 204, 401, 403, 404, 422.</li><li>Représentations JSON, pagination et filtres par paramètres.</li></ul>'
            .'<h3>2. Sécurité</h3><ul><li>Authentification par session (Sanctum) et protection CSRF.</li><li>Autorisations par permissions (RBAC) vérifiées côté serveur.</li><li>Validation systématique des entrées, limitation de débit.</li></ul>'
            .'<h3>3. Exercice</h3><p>Exposer la ressource <b>/api/projets</b> (CRUD), documenter le contrat OpenAPI et écrire les tests de bout en bout.</p></div></body></html>'
        )->output();
        $ajouter('Support — API REST sécurisée', 'support-api-rest.pdf', 'application/pdf', $pdf, $modules[2] ?? null);

        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(900, 420);
            $blanc = imagecolorallocate($img, 255, 255, 255); $bleu = imagecolorallocate($img, 13, 90, 138); $clair = imagecolorallocate($img, 225, 243, 251); $orange = imagecolorallocate($img, 237, 143, 0);
            imagefill($img, 0, 0, $blanc);
            foreach ([[60, 'PROMOTION', ['id', 'libelle', 'date_debut']], [350, 'INSCRIPTION', ['user_id', 'promotion_id', 'date']], [640, 'UTILISATEUR', ['id', 'nom', 'email']]] as [$x, $titre, $champs]) {
                imagefilledrectangle($img, $x, 120, $x + 200, 300, $clair); imagerectangle($img, $x, 120, $x + 200, 300, $bleu);
                imagefilledrectangle($img, $x, 120, $x + 200, 160, $bleu); imagestring($img, 5, $x + 14, 132, $titre, $blanc);
                foreach ($champs as $k => $c) { imagestring($img, 4, $x + 16, 180 + $k * 32, $c, $bleu); }
            }
            imagesetthickness($img, 3); imageline($img, 260, 210, 350, 210, $orange); imageline($img, 550, 210, 640, 210, $orange);
            imagestring($img, 5, 60, 40, 'MCD simplifie - inscriptions (1,n) - (0,n)', $bleu);
            ob_start(); imagepng($img); $png = ob_get_clean(); imagedestroy($img);
            $ajouter('Schéma MCD — inscriptions', 'schema-mcd-inscriptions.png', 'image/png', $png, $modules[1] ?? null);
        }

        $ajouter('Mémo — commandes Git du binôme', 'memo-git.md', 'text/plain', "# Mémo Git\n\n"
            ."git switch develop && git pull          # partir de develop à jour\n"
            ."git switch -c feat/ma-fonctionnalite    # une branche par fonctionnalité\n"
            ."git add -p && git commit                # commits courts et explicites\n"
            ."git push -u origin feat/ma-fonctionnalite\n\n"
            ."Puis : Pull Request vers develop, relecture par le binôme, fusion.\n", $modules[0] ?? null);
    }
}
