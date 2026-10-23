<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Actualités de l'organisme de formation continue, reprises de expernet.re (titre, extrait, lien d'origine). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fc_actualites', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('titre', 200);
            $table->string('categorie', 40);
            $table->string('extrait', 400);
            $table->string('url_source', 300);
            $table->unsignedSmallInteger('ordre')->default(0);   // ordre du site d'origine, plus récent en premier
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fc_actualites');
    }
};
