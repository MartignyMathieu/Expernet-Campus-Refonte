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
                        // Absentéisme simulé : « Tom » s'absente souvent, « Hugo » ponctuellement - pour démontrer les alertes.
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
        $this->espacesDemo($promo, $admin, $f1, $f2, $etudiants[0], $modules);
        $this->suiviDemo($promo, $f1, $modules);

        // Session d'examen + jury
        $examen = SessionExamen::updateOrCreate(['promotion_id' => $promo->id, 'intitule' => 'Évaluation intermédiaire - AT1'], [
            'debut' => Carbon::today()->addWeeks(3)->setTime(8, 30), 'fin' => Carbon::today()->addWeeks(3)->setTime(12, 0), 'lieu' => 'Le Port - Salle 528',
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
                .'<h1 style="font-family:sans-serif;color:#0D5A8A">TP noté - API REST sécurisée</h1>'
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

    /** Devoirs et cahier de textes de démonstration (idempotent). */
    private function suiviDemo(Promotion $promo, User $f1, $modules): void
    {
        $web = $modules[2] ?? $modules->first();
        // Le devoir porte sur un module que formateur@ enseigne réellement (séance existante)
        $module = Seance::where('promotion_id', $promo->id)->where('formateur_id', $f1->id)->value('module_id') ?? $web->id;
        \App\Models\Devoir::firstOrCreate(['promotion_id' => $promo->id, 'titre' => 'TP 3 - API REST sécurisée'], [
            'module_id' => $module, 'auteur_id' => $f1->id, 'coefficient' => 2, 'echeance' => Carbon::today()->addDays(5)->setTime(18, 0),
            'consignes' => "Exposer la ressource /api/projets (CRUD complet), protégée par authentification et rôles.\nDocumenter le contrat OpenAPI et écrire au moins cinq tests automatisés.\nRendu : archive ZIP du projet ou lien du dépôt Git dans un fichier texte.",
        ]);

        // Cahier de textes des séances déjà passées de la semaine, et travail pour la prochaine séance
        $sujets = ['Installation de l\'environnement et premiers pas', 'Modélisation : MCD, MLD et normalisation', 'Routes, contrôleurs et validation', 'Composants et état côté interface', 'Planification et suivi en mode agile'];
        Seance::where('promotion_id', $promo->id)->whereDate('date', '>=', Carbon::today()->subDays(7))->whereDate('date', '<=', Carbon::today())
            ->whereNull('cahier_maj_at')->with('module')->get()
            ->each(function (Seance $s) use ($sujets) {
                $i = (int) $s->module_id % count($sujets);
                $s->forceFill([
                    'contenu_vu' => $sujets[$i].' : notions vues en cours, démonstration puis exercices guidés.',
                    'travail_a_faire' => $s->date->isToday() ? 'Relire le support du module et terminer l\'exercice commencé en séance.' : null,
                    'travail_pour' => $s->date->isToday() ? Carbon::today()->addWeekday()->toDateString() : null,
                    'cahier_maj_at' => now(),
                ])->save();
            });
    }

    /**
     * Un document de démonstration par espace (pédagogie, TP, examens, administratif, stage, rendus).
     * Idempotent (un document déjà présent n'est pas recréé) ; ajouté aussi sur une base déjà peuplée.
     */
    private function espacesDemo(Promotion $promo, User $admin, User $f1, User $f2, User $etudiant, $modules): void
    {
        Document::where('promotion_id', $promo->id)->where('titre', 'Mémo - commandes Git du binôme')->where('espace', 'cours')->update(['espace' => 'exercices']);

        $disque = Storage::disk('local');
        $pdf = fn (string $titre, string $corps) => \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
            '<html><head><meta charset="utf-8"><title>'.e($titre).'</title></head><body><div style="font-family:Helvetica,sans-serif;color:#14202B">'
            .'<p style="color:#009BDD;font-weight:bold;letter-spacing:2px">EXPERNET CAMPUS · CDA 2026-2028</p>'
            .'<h1 style="color:#0D5A8A">'.e($titre).'</h1>'.$corps.'</div></body></html>'
        )->output();

        $documents = [
            ['pedagogie', $f1, 'Programme de formation - CDA', 'Activités types, modules, volumes horaires et modalités d\'évaluation.', 'programme-cda.pdf', 'application/pdf', fn () => $pdf('Programme de formation - CDA',
                '<p>Titre professionnel Concepteur Développeur d\'Applications (RNCP37873), niveau 6.</p><h3>Activités types</h3><ol><li>Développer une application sécurisée</li>'
                .'<li>Concevoir et développer une application sécurisée organisée en couches</li><li>Préparer le déploiement d\'une application sécurisée</li></ol>'
                .'<h3>Modules</h3><ul><li>Environnement de développement - 35 h</li><li>Bases de données - 70 h</li><li>Back-end - 105 h</li><li>Interfaces - 70 h</li><li>Conduite de projet - 35 h</li></ul>')],
            ['exercices', $f2, 'TP 2 - Modéliser les inscriptions', 'À rendre dans « Mes rendus » avant vendredi 17 h.', 'tp2-modelisation.md', 'text/plain', fn () => "# TP 2 - Modéliser les inscriptions\n\n"
                ."1. Établir le MCD (promotion, inscription, utilisateur).\n2. Le traduire en MLD en 3e forme normale.\n3. Écrire la migration Laravel.\n\n"
                ."Rendu : un PDF ou une archive ZIP dans l'espace « Mes rendus ».\n"],
            ['examens', $admin, 'Convocation - évaluation intermédiaire AT1', 'Date, salle, pièces à présenter et déroulé de l\'épreuve.', 'convocation-at1.pdf', 'application/pdf', fn () => $pdf('Convocation - évaluation intermédiaire AT1',
                '<p>Vous êtes convoqué(e) à l\'évaluation intermédiaire de l\'activité type 1.</p><ul><li>Lieu : Le Port - Salle 528</li><li>Horaires : 8 h 30 - 12 h</li>'
                .'<li>Se munir d\'une pièce d\'identité et de son ordinateur portable chargé.</li></ul>')],
            ['administratif', $admin, 'Règlement intérieur', 'Règles de vie, assiduité, absences et justificatifs.', 'reglement-interieur.pdf', 'application/pdf', fn () => $pdf('Règlement intérieur',
                '<h3>Assiduité</h3><p>La présence est obligatoire. Toute absence doit être justifiée sous 48 heures (justificatif déposé dans « Mes rendus »).</p>'
                .'<h3>Émargement</h3><p>L\'émargement numérique se fait par demi-journée, à l\'ouverture de la séance par le formateur.</p>')],
            ['stage', $f1, 'Offre d\'alternance - développeur web (Saint-Denis)', 'Entreprise partenaire, rythme 3 semaines / 1 semaine.', 'offre-alternance.md', 'text/plain', fn () => "# Offre d'alternance - Développeur web\n\n"
                ."Entreprise partenaire à Saint-Denis. Stack : Laravel, Vue, PostgreSQL.\nRythme : 3 semaines en entreprise / 1 semaine au campus.\n\nCandidature : CV + lettre à transmettre à la chargée de relations entreprises.\n"],
            ['rendus', $etudiant, 'Justificatif d\'absence - rendez-vous médical', null, 'justificatif-absence.md', 'text/plain', fn () => "Justificatif d'absence pour la demi-journée du matin (rendez-vous médical).\n"],
        ];

        foreach ($documents as [$espace, $auteur, $titre, $description, $nom, $mime, $contenu]) {
            if (Document::where('promotion_id', $promo->id)->where('titre', $titre)->exists()) {
                continue;
            }
            $donnees = $contenu();
            $chemin = 'documents/'.$promo->id.'/'.Str::uuid().'.'.pathinfo($nom, PATHINFO_EXTENSION);
            $disque->put($chemin, $donnees);
            Document::create([
                'promotion_id' => $promo->id, 'auteur_id' => $auteur->id, 'espace' => $espace, 'titre' => $titre, 'description' => $description,
                'chemin' => $chemin, 'nom_original' => $nom, 'mime' => $mime, 'taille' => strlen($donnees),
                'module_id' => $espace === 'exercices' ? ($modules[1] ?? null)?->id : null,
            ]);
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
            '<html><head><meta charset="utf-8"><title>Support - API REST sécurisée</title></head><body>'
            ."<div style=\"$css\"><p style=\"color:#009BDD;font-weight:bold;letter-spacing:2px\">EXPERNET CAMPUS · CDA 2026-2028</p>"
            .'<h1 style="color:#0D5A8A">Développement web back-end</h1><h2>Séance 3 - Concevoir une API REST sécurisée</h2>'
            .'<h3>1. Principes REST</h3><ul><li>Ressources identifiées par des URI, verbes HTTP explicites (GET, POST, PATCH, DELETE).</li><li>Codes de statut : 200, 201, 204, 401, 403, 404, 422.</li><li>Représentations JSON, pagination et filtres par paramètres.</li></ul>'
            .'<h3>2. Sécurité</h3><ul><li>Authentification par session (Sanctum) et protection CSRF.</li><li>Autorisations par permissions (RBAC) vérifiées côté serveur.</li><li>Validation systématique des entrées, limitation de débit.</li></ul>'
            .'<h3>3. Exercice</h3><p>Exposer la ressource <b>/api/projets</b> (CRUD), documenter le contrat OpenAPI et écrire les tests de bout en bout.</p></div></body></html>'
        )->output();
        $ajouter('Support - API REST sécurisée', 'support-api-rest.pdf', 'application/pdf', $pdf, $modules[2] ?? null);

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
            $ajouter('Schéma MCD - inscriptions', 'schema-mcd-inscriptions.png', 'image/png', $png, $modules[1] ?? null);
        }

        $ajouter('Mémo - commandes Git du binôme', 'memo-git.md', 'text/plain', "# Mémo Git\n\n"
            ."git switch develop && git pull          # partir de develop à jour\n"
            ."git switch -c feat/ma-fonctionnalite    # une branche par fonctionnalité\n"
            ."git add -p && git commit                # commits courts et explicites\n"
            ."git push -u origin feat/ma-fonctionnalite\n\n"
            ."Puis : Pull Request vers develop, relecture par le binôme, fusion.\n", $modules[0] ?? null);
    }
}
