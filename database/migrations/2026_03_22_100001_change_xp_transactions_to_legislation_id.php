<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xp_transactions', function (Blueprint $table) {
            $table->dropForeign(['legal_reference_id']);
            $table->dropIndex(['legal_reference_id']);
            $table->dropColumn('legal_reference_id');
        });

        Schema::table('xp_transactions', function (Blueprint $table) {
            $table->foreignId('legislation_id')->nullable()->after('source_id')->constrained()->nullOnDelete();
            $table->index('legislation_id');
        });
    }

    public function down(): void
    {
        Schema::table('xp_transactions', function (Blueprint $table) {
            $table->dropForeign(['legislation_id']);
            $table->dropIndex(['legislation_id']);
            $table->dropColumn('legislation_id');
        });

        Schema::table('xp_transactions', function (Blueprint $table) {
            $table->foreignId('legal_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->index('legal_reference_id');
        });
    }
};
