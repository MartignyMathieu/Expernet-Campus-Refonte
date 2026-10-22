<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Filiere;
use App\Models\Formation;
use Illuminate\Database\Seeder;

/**
 * Catalogue réel repris de expernet-campus.re (consulté le 22/10/2026).
 * Hypothèse à confirmer avec Expernet : l'éligibilité CPF n'est pas publiée sur le site ;
 * les titres inscrits au RNCP sont marqués éligibles par défaut (modifiable par l'admin).
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $lePort = Campus::updateOrCreate(['nom' => 'La Réunion — Le Port'], ['adresse' => 'Parc 2000, 3 avenue Théodore Drouhet, 97420 Le Port']);
        $mayotte = Campus::updateOrCreate(['nom' => 'Mayotte'], ['adresse' => null]);

        $info = Filiere::updateOrCreate(['code' => 'informatique'], ['libelle' => 'Informatique']);
        $mkt = Filiere::updateOrCreate(['code' => 'marketing'], ['libelle' => 'Marketing digital']);

        $modalites = ['En alternance (contrat d\'apprentissage ou de professionnalisation)', 'Sélection sur dossier : CV, lettre de motivation et entretien'];

        $formations = [
            ['tsrs-bac2', $info, 'Technicien Systèmes Réseaux et Sécurité', 'Participe à la mise en service et au maintien en condition opérationnelle de l\'infrastructure informatique.', 5, 'RNCP36462', 'IPI', '29 semaines en formation, 73 semaines en entreprise sur 2 ans', '2026-09-28', [$lePort, $mayotte],
                ['Déployer, installer et configurer des infrastructures matérielles, virtuelles, systèmes et réseau', 'Sécuriser un parc informatique', 'Reconditionner du matériel et sensibiliser aux pratiques écoresponsables', 'Accompagner les utilisateurs sur les plateformes collaboratives'],
                ['Bac STI2D, Bac Pro SN, Bac S ou niveau 4 en informatique', 'Ou autre Bac avec des compétences prononcées en informatique'],
                ['Technicien·ne support', 'Technicien·ne systèmes et réseaux', 'Technicien·ne d\'exploitation cloud', 'Technicien·ne sécurité informatique']],
            ['cda-bac4', $info, 'Concepteur Développeur d\'Applications', 'Anime et conduit la réalisation de projets applicatifs, de l\'interface à la base de données.', 6, 'RNCP37873', 'Titre certifié par l\'État', '31 semaines en formation, 70 semaines en entreprise sur 2 ans', '2026-10-19', [$lePort, $mayotte],
                ['Concevoir et développer des composants d\'interface utilisateur', 'Concevoir et développer une base de données', 'Concevoir et développer une application multicouche'],
                ['Bac+2 / niveau 5 en informatique', 'Ou Bac+2 avec des compétences prononcées en informatique'],
                ['Analyste concepteur', 'Concepteur développeur web', 'Concepteur développeur d\'applications mobiles']],
            ['asr-bac4', $info, 'Administrateur Système et Réseau', 'Conçoit, planifie et met en œuvre des infrastructures réseaux et des systèmes d\'information.', 6, 'RNCP41776', 'Titre certifié par l\'État', '30 semaines en formation, 70 semaines en entreprise sur 2 ans', '2026-10-05', [$lePort, $mayotte],
                ['Administrer les systèmes serveurs', 'Administrer et sécuriser les réseaux', 'Concevoir un système d\'information sécurisé', 'Piloter les projets d\'évolution du SI'],
                ['Bac+2 / niveau 5 en informatique', 'Ou Bac+2 avec des compétences prononcées en informatique'],
                ['Administrateur système et/ou réseau', 'Administrateur de bases de données', 'Spécialiste système et réseau']],
            ['hacking-cybersecurite-bac5', $info, 'Hacking & Cybersécurité', 'Participe directement aux projets de l\'entreprise liés à la sécurité numérique.', 7, 'RNCP36399', 'ESD — Expert en Sécurité Digitale', '15 semaines en formation, 40 semaines en entreprise sur 1 an', '2026-11-23', [$lePort],
                ['Accompagner la mise en œuvre d\'une politique de sécurité', 'Concevoir un plan stratégique de sécurité', 'Conduire un audit de sécurité'],
                ['Bac+3 / niveau 6 en informatique', 'Ou Bac+3/4 avec des compétences prononcées en informatique'],
                ['Expert·e en sécurité numérique', 'Consultant·e SSI', 'Auditeur·rice SSI', 'Assistant·e RSSI']],
            ['directeur-des-projets-it-bac5', $info, 'Directeur des projets IT', 'Pilote des projets de mise en œuvre et d\'optimisation du système d\'information.', 7, 'RNCP36009', 'Directeur des projets informatiques', '15 semaines en formation, 40 semaines en entreprise sur 1 an', '2026-11-16', [$lePort],
                ['Gérer les processus et la qualité des projets informatiques', 'Gérer les ressources des projets', 'Traduire les besoins métiers en besoins fonctionnels et techniques'],
                ['Diplôme de niveau 6 (Bac+3/4) en informatique ou en maîtrise d\'ouvrage', 'Formation continue : 3 ans d\'expérience professionnelle'],
                ['Chef·fe de projet IT', 'Responsable projet', 'Consultant·e MOA/MOE']],
            ['bac2-graphiste-multimedia-ia', $mkt, 'Graphiste, Multimédia & IA', 'Conçoit et réalise le design graphique d\'interfaces numériques en respectant la charte.', 5, 'RNCP40237', 'ECAD Consultants — IESA Multimédia', '3 semaines en entreprise / 1 semaine à l\'école', '2026-10-12', [$lePort],
                ['Concevoir une proposition créative cohérente avec une identité de marque', 'Réaliser des contenus graphiques print et digital', 'Intégrer l\'IA générative dans le processus de création'],
                ['Baccalauréat, DAEU ou certification de niveau 4'],
                ['Graphiste multimédia / web', 'Infographiste', 'Webdesigner']],
            ['chef-de-projet-digital-bac3', $mkt, 'Chef de projet digital', 'Coordonne les opérations de marketing digital et de webmarketing.', 6, 'RNCP42627', 'ECAD Consultants — IESA Multimédia', '2 semaines en entreprise / 1 semaine à l\'école', '2026-09-07', [$lePort],
                ['Gérer un projet de production d\'une solution digitale', 'Mettre en œuvre une stratégie webmarketing', 'Créer des contenus digitaux'],
                ['Bac+2 (DUT TC, BTS COM…) ou 120 crédits ECTS avec appétence pour le marketing digital'],
                ['Chef de projet digital', 'Spécialiste SEO / SEA', 'Community manager', 'UX / UI designer']],
            ['expert-en-strategie-digitale-bac5', $mkt, 'Expert en stratégie digitale', 'Conseille l\'entreprise en définissant sa stratégie de communication digitale.', 7, 'RNCP36287', 'ECAD Consultants — IESA Multimédia', '2 semaines en entreprise / 1 semaine à l\'école', '2026-10-05', [$lePort],
                ['Définir une stratégie digitale', 'Déployer un dispositif digital de communication et marketing', 'Piloter un dispositif digital'],
                ['Titre ou diplôme de niveau 6 (Bac+3, 180 crédits ECTS) dans le digital, le commerce ou la communication'],
                ['Responsable e-commerce', 'Manager de projet digital', 'Growth hacker', 'Lead UX']],
        ];

        foreach ($formations as [$slug, $filiere, $intitule, $accroche, $niveau, $rncp, $certif, $rythme, $rentree, $campus, $objectifs, $prerequis, $debouches]) {
            $f = Formation::updateOrCreate(['slug' => $slug], [
                'filiere_id' => $filiere->id, 'intitule' => $intitule, 'accroche' => $accroche, 'niveau_rncp' => $niveau,
                'code_rncp' => $rncp, 'certificateur' => $certif, 'eligible_cpf' => true, 'rythme' => $rythme,
                'prochaine_rentree' => $rentree, 'publiee' => true,
            ]);
            $f->campus()->sync(collect($campus)->pluck('id'));
            $f->elements()->delete();
            foreach (['objectif' => $objectifs, 'prerequis' => $prerequis, 'debouche' => $debouches, 'modalite' => $modalites] as $type => $items) {
                foreach (array_values($items) as $i => $contenu) {
                    $f->elements()->create(['type' => $type, 'ordre' => $i, 'contenu' => $contenu]);
                }
            }
        }
    }
}
