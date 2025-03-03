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
        Schema::create('law_articles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('legal_reference_id')
            ->constrained()
            ->onDelete('cascade')
            ->onUpdate('cascade');
            $table->text('original_content');  // Conteúdo original completo do artigo
            $table->text('practice_content')->nullable();  // Conteúdo com lacunas para prática
            $table->string('article_reference')->nullable();  // Número/identificação do artigo (ex: "Art. 5º")
            $table->integer('difficulty_level')->default(1); // Nível de dificuldade (1-5)
            $table->integer('position')->default(10); // Posição na sequência de aprendizado
            $table->boolean('is_active')->default(true); // Status do artigo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('law_articles');
    }
};
