<?php

namespace Database\Seeders;

use App\Models\FcActualite;
use App\Models\FcDomaine;
use App\Models\FcStage;
use Illuminate\Database\Seeder;

/**
 * Catalogue de formation continue repris intégralement de expernet.re (149 stages, 14 domaines),
 * extrait le 22/10/2026 : database/data/formation-continue.json.
 * Les modalités communes (moyens, méthodes, évaluation) sont stockées une fois dans config('expernet.formation_continue.modalites').
 */
class FormationContinueSeeder extends Seeder
{
    public const FICHIER = 'data/formation-continue.json';

    public const ACTUALITES = 'data/fc-actualites.json';

    public function run(): void
    {
        $donnees = json_decode(file_get_contents(database_path(self::FICHIER)), true, flags: JSON_THROW_ON_ERROR);

        $domaines = [];
        foreach ($donnees['domaines'] as $i => $d) {
            $domaines[$d['slug']] = FcDomaine::updateOrCreate(['slug' => $d['slug']], ['libelle' => $d['libelle'], 'icone' => $d['icone'], 'ordre' => $i]);
        }

        // Stages de l'ancien jeu réduit (intitulés sans fiche) : remplacés par le catalogue complet.
        FcStage::whereNull('slug')->delete();

        $ordre = [];
        foreach ($donnees['stages'] as $s) {
            $domaine = $domaines[$s['domaine']];
            $ordre[$domaine->id] = ($ordre[$domaine->id] ?? -1) + 1;
            FcStage::updateOrCreate(['slug' => $s['slug']], [
                'fc_domaine_id' => $domaine->id, 'intitule' => $s['intitule'], 'sous_theme' => $s['sous_theme'], 'ordre' => $ordre[$domaine->id],
                'public' => $s['public'], 'duree' => $s['duree'], 'tarifs' => $s['tarifs'], 'delai_acces' => $s['delai_acces'],
                'prochaine_session' => $s['prochaine_session'], 'objectifs' => $s['objectifs'], 'prerequis' => $s['prerequis'],
                'programme' => $s['programme'], 'moyens' => $s['moyens'], 'methodes' => $s['methodes'], 'evaluation' => $s['evaluation'],
                'programme_pdf' => $s['programme_pdf'], 'url_source' => $s['url_source'],
            ]);
        }

        // Actualités de l'organisme (ordre du site d'origine)
        $actualites = json_decode(file_get_contents(database_path(self::ACTUALITES)), true, flags: JSON_THROW_ON_ERROR)['actualites'];
        foreach ($actualites as $i => $a) {
            FcActualite::updateOrCreate(['slug' => $a['slug']], [
                'titre' => $a['titre'], 'categorie' => $a['categorie'], 'extrait' => $a['extrait'], 'url_source' => $a['url_source'], 'ordre' => $i,
                'publie_le' => $a['publie_le'] ?? null, 'image' => $a['image'] ?? null, 'theme' => $a['theme'] ?? 'organisme',
            ]);
        }
    }
}
