<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Um trecho contínuo de blocos de uma lei: com material (rascunho/publicado) ou pulado
        Schema::create('study_materials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('legislation_id')->constrained('legislations')->cascadeOnDelete();
            $table->foreignId('first_block_id')->nullable()->constrained('legislation_segments')->nullOnDelete();
            $table->foreignId('last_block_id')->nullable()->constrained('legislation_segments')->nullOnDelete();
            $table->json('block_ids')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('title')->nullable();
            $table->longText('summary')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('skip_reason', 500)->nullable();
            $table->string('generated_by_model', 100)->nullable();
            $table->string('skill_version', 30)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['legislation_id', 'status']);
        });

        Schema::create('study_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('study_material_id')->constrained('study_materials')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('board', 20);
            $table->string('type', 20);
            $table->text('statement');
            $table->json('options')->nullable();
            $table->string('correct_answer', 10);
            $table->text('explanation');
            $table->string('legal_basis', 500);
            $table->timestamps();

            $table->index(['study_material_id', 'position']);
        });

        Schema::create('user_study_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('study_material_id')->constrained('study_materials')->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'study_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_study_materials');
        Schema::dropIfExists('study_questions');
        Schema::dropIfExists('study_materials');
    }
};
