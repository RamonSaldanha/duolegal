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
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('law_article_id')->constrained()->onDelete('cascade');
            $table->integer('correct_answers')->default(0);
            $table->integer('total_answers')->default(0);
            $table->decimal('percentage', 5, 2)->default(0); // Porcentagem de acerto (0-100)
            $table->integer('attempts')->default(0); // Número de tentativas
            $table->integer('best_score')->default(0); // Melhor pontuação
            $table->timestamp('completed_at')->nullable(); // Quando o artigo foi concluído
            $table->boolean('is_completed')->default(false); // Marca se o artigo foi concluído
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};