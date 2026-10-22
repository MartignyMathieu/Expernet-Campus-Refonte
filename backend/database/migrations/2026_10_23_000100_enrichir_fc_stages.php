<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formation continue : fiche complète de chaque stage, reprise du catalogue expernet.re
 * (public, durée, tarifs, objectifs, prérequis, programme, modalités, prochaine session).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fc_stages', function (Blueprint $table) {
            $table->dropUnique(['fc_domaine_id', 'intitule']); // deux stages peuvent porter le même intitulé
        });

        Schema::table('fc_stages', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('fc_domaine_id');
            $table->string('sous_theme', 60)->nullable()->after('intitule');
            $table->json('public')->nullable();
            $table->string('duree', 30)->nullable();
            $table->json('tarifs')->nullable();              // { inter, intra, unique } en euros HT, ou « sur demande »
            $table->string('delai_acces', 30)->nullable();
            $table->date('prochaine_session')->nullable();
            $table->json('objectifs')->nullable();
            $table->json('prerequis')->nullable();
            $table->json('programme')->nullable();           // lignes ; « ## » marque un module
            $table->json('moyens')->nullable();              // null = modalités standard de l'organisme
            $table->json('methodes')->nullable();
            $table->json('evaluation')->nullable();
            $table->string('programme_pdf', 255)->nullable();
            $table->string('url_source', 255)->nullable();
            $table->index(['fc_domaine_id', 'ordre']);
            $table->index('prochaine_session');
        });
    }

    public function down(): void
    {
        Schema::table('fc_stages', function (Blueprint $table) {
            $table->dropIndex(['fc_domaine_id', 'ordre']);
            $table->dropIndex(['prochaine_session']);
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'sous_theme', 'public', 'duree', 'tarifs', 'delai_acces', 'prochaine_session', 'objectifs',
                'prerequis', 'programme', 'moyens', 'methodes', 'evaluation', 'programme_pdf', 'url_source']);
        });

        Schema::table('fc_stages', function (Blueprint $table) {
            $table->unique(['fc_domaine_id', 'intitule']);
        });
    }
};
