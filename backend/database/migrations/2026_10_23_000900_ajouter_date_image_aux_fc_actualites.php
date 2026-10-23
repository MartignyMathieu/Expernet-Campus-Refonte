<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Actualités de formation continue : date de publication, image d'origine et thème (vignette de repli). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fc_actualites', function (Blueprint $table) {
            $table->date('publie_le')->nullable()->after('url_source');
            $table->string('image', 200)->nullable()->after('publie_le');
            $table->string('theme', 30)->default('organisme')->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('fc_actualites', function (Blueprint $table) {
            $table->dropColumn(['publie_le', 'image', 'theme']);
        });
    }
};
