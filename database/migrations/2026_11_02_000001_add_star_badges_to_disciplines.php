<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Disciplina (slug) => prefixo dos PNGs em public/img/badges. */
    private const BADGE_KEYS = [
        'administrativo' => 'administrativo',
        'civil' => 'civil',
        'constitucional' => 'constitucional',
        'criminal' => 'criminal',
        'processo' => 'processo',
        'trabalhista' => 'trabalhista',
        'tributario' => 'tributario',
    ];

    public function up(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->string('badge_key')->nullable()->after('color');
            $table->json('star_thresholds')->nullable()->after('badge_key');
        });

        // XP anterior às legislações (origem "migration"), distribuído por área.
        Schema::create('discipline_xp_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp');
            $table->timestamps();

            $table->unique(['user_id', 'discipline_id']);
        });

        // Maior número de estrelas já conquistado: a estrela nunca é perdida.
        Schema::create('user_discipline_stars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->timestamps();

            $table->unique(['user_id', 'discipline_id']);
        });

        foreach (self::BADGE_KEYS as $slug => $key) {
            DB::table('disciplines')->where('slug', $slug)->update(['badge_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_discipline_stars');
        Schema::dropIfExists('discipline_xp_credits');

        Schema::table('disciplines', function (Blueprint $table) {
            $table->dropColumn(['badge_key', 'star_thresholds']);
        });
    }
};
