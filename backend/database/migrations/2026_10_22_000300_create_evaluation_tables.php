<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Évaluations : notes, sessions d'examen, jurys, référentiel de compétences et grilles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('formateur_id')->constrained('users')->restrictOnDelete();
            $table->string('intitule', 150);
            $table->decimal('valeur', 4, 2);           // sur 20
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->date('date_evaluation');
            $table->timestamps();
        });

        Schema::create('sessions_examen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('intitule', 150);
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->string('lieu', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('jury_membres', function (Blueprint $table) {
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('fonction', 12);            // president | membre
            $table->primary(['session_examen_id', 'user_id']);
        });

        Schema::create('competences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('libelle', 250);
            $table->timestamps();
            $table->unique(['formation_id', 'code']);
        });

        Schema::create('evaluations_competence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('competence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluateur_id')->constrained('users')->restrictOnDelete();
            $table->string('niveau', 12);              // non_acquis | en_cours | acquis | maitrise
            $table->string('commentaire', 500)->nullable();
            $table->timestamps();
            $table->unique(['session_examen_id', 'etudiant_id', 'competence_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('titre', 150);
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('mime', 100);
            $table->unsignedInteger('taille');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('evaluations_competence');
        Schema::dropIfExists('competences');
        Schema::dropIfExists('jury_membres');
        Schema::dropIfExists('sessions_examen');
        Schema::dropIfExists('notes');
    }
};
