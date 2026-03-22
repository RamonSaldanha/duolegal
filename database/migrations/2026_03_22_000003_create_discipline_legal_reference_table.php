<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_legal_reference', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_reference_id')->constrained()->cascadeOnDelete();

            $table->unique(['discipline_id', 'legal_reference_id'], 'discipline_legal_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_legal_reference');
    }
};
