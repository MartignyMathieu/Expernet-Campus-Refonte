<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module public : campus, filières, formations et leurs éléments descriptifs.
 * Les listes (objectifs, prérequis, débouchés, modalités) sont stockées dans
 * une table dédiée pour respecter la 1NF (pas d'attribut multivalué).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80)->unique();
            $table->string('adresse', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('filieres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();      // informatique | marketing
            $table->string('libelle', 80);
            $table->timestamps();
        });

        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filiere_id')->constrained('filieres')->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('intitule', 150);
            $table->string('accroche', 300);
            $table->unsignedTinyInteger('niveau_rncp');           // 5, 6 ou 7 (cadre national)
            $table->string('code_rncp', 20)->nullable();          // ex : RNCP37873
            $table->string('certificateur', 200)->nullable();
            $table->boolean('eligible_cpf')->default(false);
            $table->string('rythme', 200)->nullable();
            $table->date('prochaine_rentree')->nullable();
            $table->boolean('publiee')->default(true);
            $table->timestamps();
            $table->index(['filiere_id', 'niveau_rncp', 'eligible_cpf']);
        });

        Schema::create('formation_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                // objectif | prerequis | debouche | modalite
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->string('contenu', 500);
            $table->timestamps();
            $table->index(['formation_id', 'type']);
        });

        Schema::create('formation_campus', function (Blueprint $table) {
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campus')->cascadeOnDelete();
            $table->primary(['formation_id', 'campus_id']);
        });

        Schema::create('candidatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->restrictOnDelete();
            $table->string('prenom', 80);
            $table->string('nom', 80);
            $table->string('email');
            $table->string('telephone', 20)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('consentement_rgpd_at');
            $table->string('statut', 20)->default('recue');   // recue | en_cours | acceptee | refusee
            $table->timestamps();
        });

        Schema::create('messages_contact', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 160);
            $table->string('email');
            $table->string('sujet', 150);
            $table->text('message');
            $table->timestamp('consentement_rgpd_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages_contact');
        Schema::dropIfExists('candidatures');
        Schema::dropIfExists('formation_campus');
        Schema::dropIfExists('formation_elements');
        Schema::dropIfExists('formations');
        Schema::dropIfExists('filieres');
        Schema::dropIfExists('campus');
    }
};
