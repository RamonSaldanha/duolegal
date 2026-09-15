<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda a configuração do avatar (ids de peça + cores hex) como JSON.
     *
     * Fica nulo para quem nunca editou o avatar — nesse caso a interface cai no
     * fallback de iniciais que já existia.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('avatar_config')->nullable()->after('lives');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_config');
        });
    }
};
