<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Sem `after('lives')`: a coluna `lives` só é criada em
            // 2025_03_09_191917, então migrar do zero quebrava aqui. A ordem das
            // colunas é cosmética e esta coluna é removida em 2026_03_22_000004.
            $table->integer('xp')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('xp');
        });
    }
};
