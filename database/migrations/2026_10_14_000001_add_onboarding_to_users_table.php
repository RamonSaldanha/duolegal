<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding de quem se cadastra pelo site.
     *
     * - `onboarding_answers`: respostas do quiz (objetivo, área, minutos por dia), para análise.
     * - `onboarding_completed_at`: fim do fluxo (concluído ou pulado). Só fica nulo
     *   para quem se cadastrou pelo site e ainda não terminou; contas antigas e
     *   cadastros pelo app já nascem com a data.
     * - `welcome_offer_expires_at`: fim das 24h da oferta de boas-vindas (15% no anual).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('onboarding_answers')->nullable()->after('avatar_config');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_answers');
            $table->timestamp('welcome_offer_expires_at')->nullable()->after('onboarding_completed_at');
        });

        // Quem já tinha conta não passa pelo onboarding (nem ganha a oferta de boas-vindas).
        DB::table('users')->whereNull('onboarding_completed_at')->update(['onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_answers', 'onboarding_completed_at', 'welcome_offer_expires_at']);
        });
    }
};
