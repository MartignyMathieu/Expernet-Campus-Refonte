<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Typographie : les tirets longs (— et –) sont remplacés par des tirets simples dans les données déjà
 * enregistrées. Les seeders utilisent désormais « - » : sans cette migration, les lignes repérées par
 * leur libellé (campus, session d'examen, documents de démonstration) seraient recréées en double.
 * Les colonnes JSON du catalogue de formation continue sont réécrites par son seeder (clé : slug).
 */
return new class extends Migration
{
    private const COLONNES = [
        'campus' => ['nom', 'adresse'],
        'formations' => ['intitule', 'accroche', 'certificateur', 'rythme'],
        'formation_elements' => ['contenu'],
        'fc_domaines' => ['libelle'],
        'fc_stages' => ['intitule', 'sous_theme'],
        'sessions_examen' => ['intitule', 'lieu'],
        'documents' => ['titre', 'description'],
        'messages' => ['contenu'],
    ];

    public function up(): void
    {
        foreach (self::COLONNES as $table => $colonnes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($colonnes as $colonne) {
                if (! Schema::hasColumn($table, $colonne)) {
                    continue;
                }
                foreach (['—', '–'] as $tiret) {
                    DB::table($table)->where($colonne, 'like', '%'.$tiret.'%')
                        ->update([$colonne => DB::raw('REPLACE('.$colonne.", '".$tiret."', '-')")]);
                }
            }
        }
    }

    public function down(): void
    {
        // Irréversible : on ne sait plus quels tirets étaient longs.
    }
};
