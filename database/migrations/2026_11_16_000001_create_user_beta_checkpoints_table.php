<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Progresso de cada desafio de etapa do mapa beta (a fase paralela ao fim de cada etapa).
     * A etapa é identificada pelo último bloco dela; a fase guarda quais materiais de estudo
     * a pessoa já concluiu dentro dela. Os materiais em si vêm do mapa, recalculados sempre.
     */
    public function up(): void
    {
        Schema::create('user_beta_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('legislation_segment_id')->constrained('legislation_segments')->cascadeOnDelete();
            $table->json('done_material_ids');
            $table->timestamps();

            $table->unique(['user_id', 'legislation_segment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_beta_checkpoints');
    }
};
