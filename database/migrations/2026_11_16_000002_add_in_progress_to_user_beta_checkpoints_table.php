<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O desafio da etapa é demorado, então a pessoa volta de onde parou: aqui fica o que ela
     * já fez dos materiais ainda não concluídos (resumo lido e respostas dadas), por id do
     * material. Ao concluir o material ele sai daqui e entra em done_material_ids.
     */
    public function up(): void
    {
        Schema::table('user_beta_checkpoints', function (Blueprint $table) {
            $table->json('in_progress')->nullable()->after('done_material_ids');
        });
    }

    public function down(): void
    {
        Schema::table('user_beta_checkpoints', function (Blueprint $table) {
            $table->dropColumn('in_progress');
        });
    }
};
