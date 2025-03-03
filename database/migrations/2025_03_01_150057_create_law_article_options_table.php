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
        Schema::create('law_article_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('law_article_id')
            ->constrained()
            ->onDelete('cascade')
            ->onUpdate('cascade');
            $table->string('word');               // A palavra que será uma alternativa
            $table->boolean('is_correct')->default(false); // Se é uma palavra do texto original (verdadeira)
            $table->integer('position')->nullable(); // Posição da palavra no texto (se aplicável)
            $table->integer('gap_order')->nullable(); // Ordem da lacuna (se aplicável)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('law_article_options');
    }
};
