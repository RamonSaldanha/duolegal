<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar XP existente para a tabela de transações
        $users = DB::table('users')->where('xp', '>', 0)->get(['id', 'xp']);

        foreach ($users as $user) {
            DB::table('xp_transactions')->insert([
                'user_id' => $user->id,
                'amount' => $user->xp,
                'source_type' => 'migration',
                'source_id' => null,
                'legal_reference_id' => null,
                'created_at' => now(),
            ]);
        }

        // Remover coluna xp da tabela users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('xp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('xp')->default(0)->after('lives');
        });

        // Recalcular XP a partir das transações
        $totals = DB::table('xp_transactions')
            ->select('user_id', DB::raw('SUM(amount) as total_xp'))
            ->groupBy('user_id')
            ->get();

        foreach ($totals as $row) {
            DB::table('users')->where('id', $row->user_id)->update(['xp' => $row->total_xp]);
        }
    }
};
