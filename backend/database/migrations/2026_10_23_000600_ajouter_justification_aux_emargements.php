<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Justification d'une absence par l'équipe pédagogique (motif, auteur, date) : statut absent → excuse. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emargements', function (Blueprint $table) {
            $table->string('motif', 255)->nullable()->after('statut');
            $table->foreignId('justifie_par')->nullable()->after('motif')->constrained('users')->nullOnDelete();
            $table->timestamp('justifie_at')->nullable()->after('justifie_par');
        });
    }

    public function down(): void
    {
        Schema::table('emargements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('justifie_par');
            $table->dropColumn(['motif', 'justifie_at']);
        });
    }
};
