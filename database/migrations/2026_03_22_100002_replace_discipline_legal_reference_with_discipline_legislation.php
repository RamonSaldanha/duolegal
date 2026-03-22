<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('discipline_legal_reference');

        Schema::create('discipline_legislation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legislation_id')->constrained()->cascadeOnDelete();

            $table->unique(['discipline_id', 'legislation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_legislation');

        Schema::create('discipline_legal_reference', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_reference_id')->constrained()->cascadeOnDelete();

            $table->unique(['discipline_id', 'legal_reference_id'], 'discipline_legal_ref_unique');
        });
    }
};
