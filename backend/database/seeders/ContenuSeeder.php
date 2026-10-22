<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\FaqQuestion;
use Illuminate\Database\Seeder;

/**
 * Contenus repris de l'ancien site (expernet-campus.re et expernet.re), consultés le 22/10/2026.
 * FAQ : questions d'origine, réponses reformulées. Blog : métadonnées des 12 derniers articles
 * et résumés rédigés pour la refonte ; le texte intégral s'importe avec `php artisan blog:import-wordpress`.
 * Formation continue : voir FormationContinueSeeder.
 */
class ContenuSeeder extends Seeder
{
    public function run(): void
    {
        $faq = [
            ['campus', 'Où se trouve le campus ?', 'Le campus est situé Local 528, Parc 2000, 3 avenue Théodore Drouhet, 97420 Le Port (La Réunion). Expernet dispose également d\'un campus à Mayotte.'],
            ['admission', 'Acceptez-vous de nouveaux étudiants ?', 'Oui. Les candidatures sont étudiées tout au long de l\'année selon les dates de rentrée de chaque cursus. Pour toute demande : campus@expernet.re.'],
            ['admission', 'Comment candidater ?', 'Remplissez le formulaire de candidature présent sur la fiche de la formation qui vous intéresse, ou écrivez à campus@expernet.re. L\'équipe répond également au 02 62 21 90 45.'],
            ['alternance', 'Quelle différence entre contrat de professionnalisation et contrat d\'apprentissage ?', 'Dans les deux cas, vous signez un contrat de travail à durée déterminée, avec les droits et devoirs d\'un salarié ; la formation et l\'examen final sont identiques. Deux points changent : la limite d\'âge (29 ans révolus pour l\'apprentissage, sauf exceptions ; aucune pour le contrat de professionnalisation) et la grille de rémunération.'],
            ['alternance', 'Y a-t-il un âge limite pour l\'alternance ?', 'Non : le contrat de professionnalisation est ouvert à tout âge. Le contrat d\'apprentissage, lui, est accessible jusqu\'à 29 ans révolus, sauf cas particuliers.'],
            ['financement', 'La formation est-elle payante ?', 'Non. En alternance, le coût de la formation est entièrement pris en charge : elle est gratuite pour l\'apprenant.'],
            ['campus', 'Peut-on suivre la formation à distance ?', 'Les cours ont lieu en présentiel dans les locaux du campus, avec ponctuellement quelques journées en distanciel au cours de l\'année.'],
            ['financement', 'Quel sera mon salaire ?', 'Il dépend du type de contrat, de votre âge et de votre niveau d\'études, selon les grilles légales de rémunération de l\'apprentissage et du contrat de professionnalisation.'],
            ['alternance', 'Qui trouve l\'entreprise d\'accueil ?', 'C\'est un travail d\'équipe : vous menez vos démarches auprès des entreprises, et le campus vous met en relation avec ses entreprises partenaires dès qu\'un besoin se présente.'],
        ];
        foreach ($faq as $i => [$theme, $q, $r]) {
            FaqQuestion::updateOrCreate(['question' => $q], ['reponse' => $r, 'theme' => $theme, 'ordre' => $i]);
        }

        $base = 'https://expernet-campus.re/';
        $articles = [
            ['pourquoi-continuer-ses-etudes-apres-son-bts-licence', '2026-09-15', 'conseils', 4, 'Après un BTS ou une licence : pourquoi continuer ses études dans le digital ?', 'Poursuivre jusqu\'au BAC+4 ou BAC+5 en alternance : ce que cela change en compétences, en responsabilités et en perspectives dans les métiers du numérique.', 'pourquoi-continuer-ses-etudes-apres-son-bts-licence/'],
            ['comment-trouver-son-alternance-en-2026', '2026-08-12', 'conseils', 4, 'Comment trouver son alternance en 2026 ?', 'Méthode, outils et accompagnement du campus pour décrocher une entreprise d\'accueil, même quand la recherche s\'annonce difficile.', 'comment-trouver-son-alternance-en-2026-expernet/'],
            ['que-faire-apres-mon-bac-hors-parcoursup', '2026-06-11', 'conseils', 5, 'Que faire après mon bac hors Parcoursup ?', 'Les voies d\'accès aux métiers du numérique en dehors de Parcoursup, dont l\'inscription directe en BAC+2 par l\'alternance.', 'que-faire-apres-mon-bac-horsparcoursup/'],
            ['pourquoi-recruter-un-alternant-gen-z', '2026-03-02', 'metiers', 4, 'Pourquoi recruter un alternant·e de la Gen Z ?', 'Ce que les jeunes alternants apportent aux entreprises : aisance numérique, capacité d\'adaptation et regard neuf sur les usages.', 'pourquoi-recruter-un-alternant-e-de-la-gen-z/'],
            ['inscriptions-ouvertes-2026', '2026-02-02', 'vie-du-campus', 3, 'Les inscriptions sont ouvertes : et si 2026 devenait ton année décisive ?', 'Informatique, cybersécurité ou marketing digital sans quitter La Réunion : les candidatures pour la rentrée 2026 sont ouvertes.', 'inscriptions-ouvertes-expernet-campus-pourquoi/'],
            ['5-competences-pour-reussir-ton-alternance', '2026-01-05', 'conseils', 3, 'Les 5 compétences indispensables pour réussir ton alternance en 2026', 'Les savoir-faire et savoir-être attendus par les entreprises pour transformer une alternance en premier emploi.', '5-competences-pour-reussir-ton-alternance/'],
            ['expernet-digital-campus-reunion', '2025-05-26', 'vie-du-campus', 2, 'Rejoins Expernet & Digital Campus à La Réunion', 'Le partenariat avec Digital Campus permet de suivre à La Réunion des cursus reconnus en marketing digital et en création.', 'rejoins-expernet-digital-campus-a-la-reunion-et-forme-toi-aux-metiers-du-digital-%f0%9f%9a%80/'],
            ['inscriptions-2025-ouvertes', '2025-01-31', 'vie-du-campus', 2, 'Inscriptions 2025 ouvertes : formez-vous aux métiers du digital', 'Ouverture des candidatures pour les cursus en informatique, cybersécurité et marketing digital de la rentrée 2025.', 'inscriptions-2025-ouvertes-chez-expernet-formez-vous-aux-metiers-du-digital-%f0%9f%9a%80/'],
            ['soyons-solidaires-soyons-roses', '2024-10-21', 'evenements', 1, '« Soyons solidaires, soyons roses »', 'Le campus s\'est mobilisé pour Octobre Rose afin de sensibiliser au dépistage du cancer du sein.', 'soyons-solidaires-soyons-roses/'],
            ['5-astuces-pour-trouver-une-alternance', '2024-09-09', 'conseils', 2, 'Les 5 astuces d\'Expernet pour trouver une alternance', 'Des conseils concrets pour se démarquer auprès des recruteurs et accélérer sa recherche d\'entreprise.', 'les-5-astuces-dexpernet-pour-trouver-une-alternance/'],
            ['retrospective-juin-2024', '2024-08-07', 'evenements', 2, 'Rétrospective d\'un mois de juin mouvementé', 'Salons, rencontres et événements : retour sur les temps forts vécus par l\'équipe du campus en juin.', 'retrospective-dun-mois-de-juin-mouvemente/'],
            ['vice-champions-24h-by-webcup', '2024-06-10', 'evenements', 2, 'Nos étudiants vice-champions du 24H by Webcup', 'Des apprenants du campus ont décroché la deuxième place de ce marathon du développement web organisé à La Réunion.', 'nos-etudiants-vice-champions-du-24h-by-webcup-%f0%9f%9a%80/'],
        ];
        foreach ($articles as [$slug, $date, $cat, $lecture, $titre, $resume, $chemin]) {
            Article::updateOrCreate(['slug' => $slug], [
                'titre' => $titre, 'resume' => $resume, 'categorie' => $cat, 'temps_lecture' => $lecture,
                'publie_le' => $date, 'image' => '/images/blog/'.$slug.'.webp', 'url_source' => $base.$chemin,
            ]);
        }
    }
}
