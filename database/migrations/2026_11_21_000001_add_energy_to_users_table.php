<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Energia do mapa novo (app e /beta/play), no lugar das vidas. As vidas continuam para o
     * site antigo e para os builds velhos do app. A recarga (+1 por hora) é calculada quando a
     * energia é lida, a partir de `energy_updated_at`; não há cron. `energy_run` guarda a
     * fase ou o desafio que a pessoa está jogando (sequência de acertos para o combo e se a
     * energia acabou no meio).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('energy')->default(25)->after('lives');
            $table->timestamp('energy_updated_at')->nullable()->after('energy');
            $table->json('energy_run')->nullable()->after('energy_updated_at');
        });

        // Energia que o Dia da prova rendeu na última entrega, para a tela do resultado
        Schema::table('user_beta_exams', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_energy')->default(0)->after('last_xp');
        });
    }

    public function down(): void
    {
        Schema::table('user_beta_exams', function (Blueprint $table) {
            $table->dropColumn('last_energy');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['energy', 'energy_updated_at', 'energy_run']);
        });
    }
};
