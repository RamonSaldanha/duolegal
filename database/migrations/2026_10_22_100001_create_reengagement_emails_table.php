<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lembretes por e-mail para quem parou de jogar (emails:reengage).
     *
     * - `users.email_opt_out_at`: a pessoa pediu para não receber mais. Nulo = recebe.
     * - `users.xp_boost_until`: fim do boost de XP que vem de presente para assinante.
     * - `reengagement_emails`: cada lembrete enviado, com o presente e quando foi aberto.
     *   A sequência zera quando a pessoa volta a jogar (ver ReengagementService).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_opt_out_at')->nullable()->after('welcome_offer_expires_at');
            $table->timestamp('xp_boost_until')->nullable()->after('email_opt_out_at');
        });

        Schema::create('reengagement_emails', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('gift', 20);
            $table->unsignedInteger('inactive_days');
            $table->timestamp('sent_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reengagement_emails');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_opt_out_at', 'xp_boost_until']);
        });
    }
};
