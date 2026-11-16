<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dia da prova do mapa beta: a fase paralela ao fim de cada etapa, com tempo, só com as
     * questões dos desafios dos quadrados da etapa. A etapa é identificada pelo último bloco
     * dela. Uma linha por pessoa e etapa: a tentativa em andamento (ou a última entregue) e o
     * que vale de uma tentativa para outra (questões que já deram XP e o melhor resultado).
     */
    public function up(): void
    {
        Schema::create('user_beta_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('legislation_segment_id')->constrained('legislation_segments')->cascadeOnDelete();
            // Tentativa: as questões na ordem sorteada e as respostas dadas, por id da questão
            $table->json('question_ids')->nullable();
            $table->json('answers')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // Cada questão dá XP uma vez só, no primeiro acerto
            $table->json('rewarded_question_ids')->nullable();
            $table->unsignedSmallInteger('best_correct')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            // XP creditado na última entrega, para a tela do resultado
            $table->unsignedInteger('last_xp')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'legislation_segment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_beta_exams');
    }
};
