<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('legal_references', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('name');
        });

        Schema::table('law_articles', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('article_reference');
            $table->unique(['legal_reference_id', 'slug'], 'law_articles_legal_reference_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('law_articles', function (Blueprint $table) {
            $table->dropUnique('law_articles_legal_reference_slug_unique');
            $table->dropColumn('slug');
        });

        Schema::table('legal_references', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
