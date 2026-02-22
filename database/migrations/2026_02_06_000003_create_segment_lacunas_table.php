<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_lacunas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('legislation_segment_id')->constrained('legislation_segments')->cascadeOnDelete();
            $table->string('word', 255);
            $table->unsignedInteger('word_position');
            $table->boolean('is_correct')->default(true);
            $table->unsignedInteger('gap_order')->nullable();
            $table->timestamps();

            $table->index('legislation_segment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_lacunas');
    }
};
