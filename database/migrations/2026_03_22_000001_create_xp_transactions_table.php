<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('source_type'); // play, challenge, legislation, migration
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('legal_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index('legal_reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_transactions');
    }
};
