<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Como cada badge de public/img/badges foi desenhado, para o admin poder editá-lo. */
    private const BADGE_CONFIGS = [
        'administrativo' => ['icon' => 'administrativo', 'text' => 'Administrativista', 'shield' => '#3cc45a', 'ribbon' => '#8b5cf6'],
        'civil' => ['icon' => 'civil', 'text' => 'Civilista', 'shield' => '#3d8bf2', 'ribbon' => '#f0364a'],
        'constitucional' => ['icon' => 'const', 'text' => 'Constitucionalista', 'shield' => '#3d8bf2', 'ribbon' => '#ffc21a'],
        'criminal' => ['icon' => 'penal', 'text' => 'Criminalista', 'shield' => '#3cc45a', 'ribbon' => '#ffc21a'],
        'processo' => ['icon' => 'processo', 'text' => 'Processualista', 'shield' => '#ffa42b', 'ribbon' => '#f0364a'],
        'trabalhista' => ['icon' => 'trabalho', 'text' => 'Trabalhista', 'shield' => '#f2583e', 'ribbon' => '#3b82f6'],
        'tributario' => ['icon' => 'tributario', 'text' => 'Tributarista', 'shield' => '#7d6cf2', 'ribbon' => '#2fbf6b'],
    ];

    public function up(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->json('badge_config')->nullable()->after('badge_key');
            // Preenchido quando o admin gera as imagens; muda a URL ao trocar a arte.
            $table->unsignedInteger('badge_version')->nullable()->after('badge_config');
        });

        foreach (self::BADGE_CONFIGS as $slug => $config) {
            DB::table('disciplines')->where('slug', $slug)->update(['badge_config' => json_encode($config)]);
        }
    }

    public function down(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->dropColumn(['badge_config', 'badge_version']);
        });
    }
};
