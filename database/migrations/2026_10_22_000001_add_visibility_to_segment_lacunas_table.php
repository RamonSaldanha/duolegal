<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lacunas passam a poder ser ocultadas em vez de apagadas: o jogo só mostra
     * as ativas, e as ocultas podem voltar (lacunas:simplify --restore).
     * auto_added marca as linhas criadas pela facilitação, para o restore removê-las.
     */
    public function up(): void
    {
        Schema::table('segment_lacunas', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('gap_order');
            $table->boolean('auto_added')->default(false)->after('is_active');

            $table->index(['legislation_segment_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('segment_lacunas', function (Blueprint $table) {
            $table->dropIndex(['legislation_segment_id', 'is_active']);
            $table->dropColumn(['is_active', 'auto_added']);
        });
    }
};
