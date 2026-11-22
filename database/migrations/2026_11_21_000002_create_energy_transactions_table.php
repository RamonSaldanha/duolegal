<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Livro da energia: cada gasto e cada ganho (a recarga por tempo fica de fora). Serve para
     * ajustar os números de config/energy.php.
     */
    public function up(): void
    {
        Schema::create('energy_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Negativo é gasto. Motivos em EnergyTransaction.
            $table->smallInteger('amount');
            $table->string('reason', 20);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'reason', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_transactions');
    }
};
