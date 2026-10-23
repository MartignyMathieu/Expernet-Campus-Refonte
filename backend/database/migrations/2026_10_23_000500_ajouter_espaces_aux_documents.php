<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espaces documentaires : chaque document est rangé dans un espace (cours, pédagogie, TP, examens,
 * administratif, stage, rendus). Les documents existants rejoignent « cours ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('espace', 30)->default('cours')->after('module_id');
            $table->string('description', 500)->nullable()->after('titre');
            $table->index(['promotion_id', 'espace']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['promotion_id', 'espace']);
            $table->dropColumn(['espace', 'description']);
        });
    }
};
