<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module ENT : promotions, inscriptions, modules, séances et émargement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->restrictOnDelete();
            $table->foreignId('campus_id')->constrained('campus')->restrictOnDelete();
            $table->string('libelle', 100);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_inscription');
            $table->timestamps();
            $table->unique(['promotion_id', 'user_id']);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('intitule', 150);
            $table->unsignedSmallInteger('volume_horaire')->default(0);
            $table->timestamps();
            $table->unique(['formation_id', 'code']);
        });

        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->restrictOnDelete();
            $table->foreignId('formateur_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->string('creneau', 12);             // matin | apres_midi
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('lieu', 120)->nullable();
            // Fenêtre d'émargement ouverte par le formateur
            $table->string('code_emargement_hash')->nullable();
            $table->timestamp('emargement_ouvert_at')->nullable();
            $table->timestamp('emargement_expire_at')->nullable();
            $table->timestamp('cloturee_at')->nullable();
            $table->timestamps();
            $table->unique(['promotion_id', 'date', 'creneau']);   // une séance par demi-journée et par promotion
            $table->index(['formateur_id', 'date']);
        });

        Schema::create('emargements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('statut', 12);              // present | absent | excuse
            $table->timestamp('signe_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->char('empreinte', 64)->nullable(); // SHA-256 de la preuve
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->unique(['seance_id', 'user_id']);  // impossible de signer deux fois
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emargements');
        Schema::dropIfExists('seances');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('inscriptions');
        Schema::dropIfExists('promotions');
    }
};
