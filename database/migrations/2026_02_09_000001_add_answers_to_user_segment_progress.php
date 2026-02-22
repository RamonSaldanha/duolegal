<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_segment_progress', function (Blueprint $table) {
            $table->json('answers')->nullable()->after('best_score');
        });
    }

    public function down(): void
    {
        Schema::table('user_segment_progress', function (Blueprint $table) {
            $table->dropColumn('answers');
        });
    }
};
