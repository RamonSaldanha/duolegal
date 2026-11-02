<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disciplinas que a pessoa não quer mostrar no perfil. Guarda as escondidas, e não
     * as escolhidas, para uma conquista nova aparecer sem precisar marcar nada.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('hidden_achievements')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hidden_achievements');
        });
    }
};
