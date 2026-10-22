<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messagerie : fichiers joints aux messages (5 par message, 10 Mo chacun, stockage privé). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('chemin');                 // disque « local » (privé), jamais exposé
            $table->string('nom_original', 255);
            $table->string('mime', 120);
            $table->unsignedInteger('taille');
            $table->timestamps();
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_pieces_jointes');
    }
};
