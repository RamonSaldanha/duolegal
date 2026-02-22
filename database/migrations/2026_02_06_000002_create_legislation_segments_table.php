<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislation_segments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('legislation_id')->constrained('legislations')->cascadeOnDelete();
            $table->text('original_text');
            $table->string('segment_type', 30)->default('text');
            $table->string('article_reference', 50)->nullable();
            $table->string('structural_marker', 100)->nullable();
            $table->unsignedInteger('position');
            $table->boolean('is_block')->default(false);
            $table->timestamps();

            $table->index(['legislation_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation_segments');
    }
};
