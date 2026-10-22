<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messagerie : modification et suppression d'un message par son auteur (trace conservée). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('modifie_at')->nullable();
            $table->timestamp('supprime_at')->nullable();   // le message reste à sa place : « Message supprimé »
            $table->index(['conversation_id', 'updated_at']); // synchronisation des modifications
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'updated_at']);
            $table->dropColumn(['modifie_at', 'supprime_at']);
        });
    }
};
