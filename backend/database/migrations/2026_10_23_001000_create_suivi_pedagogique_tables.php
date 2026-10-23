<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi pédagogique :
 *  - devoirs (consignes, échéance, barème) et correction des rendus, reportée dans les notes ;
 *  - justificatif d'absence rattaché à l'émargement ;
 *  - cahier de textes par séance (contenu vu, travail à faire) ;
 *  - notifications de l'ENT (table standard Laravel) ;
 *  - journal d'audit des actions sensibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('titre', 150);
            $table->text('consignes')->nullable();
            $table->dateTime('echeance');
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->timestamps();
            $table->index(['promotion_id', 'echeance']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('devoir_id')->nullable()->after('module_id')->constrained('devoirs')->nullOnDelete();
            $table->decimal('note', 4, 2)->nullable()->after('taille');
            $table->string('commentaire', 1000)->nullable()->after('note');
            $table->foreignId('corrige_par')->nullable()->after('commentaire')->constrained('users')->nullOnDelete();
            $table->timestamp('corrige_at')->nullable()->after('corrige_par');
            $table->foreignId('note_id')->nullable()->after('corrige_at')->constrained('notes')->nullOnDelete();
        });

        Schema::table('emargements', function (Blueprint $table) {
            $table->foreignId('justificatif_id')->nullable()->after('justifie_at')->constrained('documents')->nullOnDelete();
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->text('contenu_vu')->nullable();
            $table->text('travail_a_faire')->nullable();
            $table->date('travail_pour')->nullable();
            $table->timestamp('cahier_maj_at')->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('cible_type', 60)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->string('resume', 255);
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
            $table->index(['cible_type', 'cible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audit');
        Schema::dropIfExists('notifications');
        Schema::table('seances', fn (Blueprint $table) => $table->dropColumn(['contenu_vu', 'travail_a_faire', 'travail_pour', 'cahier_maj_at']));
        Schema::table('emargements', fn (Blueprint $table) => $table->dropConstrainedForeignId('justificatif_id'));
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('note_id');
            $table->dropConstrainedForeignId('corrige_par');
            $table->dropConstrainedForeignId('devoir_id');
            $table->dropColumn(['note', 'commentaire', 'corrige_at']);
        });
        Schema::dropIfExists('devoirs');
    }
};
