<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislation_segments', function (Blueprint $table) {
            $table->unsignedInteger('char_start')->nullable()->after('original_text');
            $table->unsignedInteger('char_end')->nullable()->after('char_start');
        });
    }

    public function down(): void
    {
        Schema::table('legislation_segments', function (Blueprint $table) {
            $table->dropColumn(['char_start', 'char_end']);
        });
    }
};
