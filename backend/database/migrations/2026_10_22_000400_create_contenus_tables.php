<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contenus éditoriaux repris de l'ancien site : FAQ, blog (articles) et offre de formation continue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question', 250);
            $table->text('reponse');
            $table->string('theme', 40);                  // admission | alternance | financement | campus
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('publiee')->default(true);
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('titre', 200);
            $table->string('resume', 400);
            $table->longText('contenu')->nullable();      // HTML assaini (rempli par l'import WordPress)
            $table->string('image', 200)->nullable();
            $table->string('categorie', 40);              // conseils | vie-du-campus | evenements | metiers
            $table->unsignedSmallInteger('temps_lecture')->default(3);
            $table->date('publie_le');
            $table->string('url_source', 300)->nullable(); // article d'origine sur l'ancien site
            $table->timestamps();
            $table->index(['categorie', 'publie_le']);
        });

        Schema::create('fc_domaines', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('libelle', 100);
            $table->string('icone', 40);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('fc_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fc_domaine_id')->constrained('fc_domaines')->cascadeOnDelete();
            $table->string('intitule', 150);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
            $table->unique(['fc_domaine_id', 'intitule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fc_stages');
        Schema::dropIfExists('fc_domaines');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('faq_questions');
    }
};
