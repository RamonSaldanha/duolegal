<?php

namespace App\Console\Commands;

use App\Models\ReengagementEmail;
use App\Services\ReengagementService;
use Illuminate\Console\Command;

class SendReengagementEmails extends Command
{
    protected $signature = 'emails:reengage
        {--dry-run : Só mostra quem receberia, sem enviar}
        {--limit=150 : Máximo de e-mails nesta execução (o resto fica para os próximos dias)}';

    protected $description = 'Manda o lembrete com presente para quem parou de jogar (intervalo crescente de 15 em 15 dias).';

    public function handle(ReengagementService $service): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $due = $service->dueUsers();
        $batch = $due->take($limit);

        $this->info("Na hora de receber: {$due->count()}. Nesta execução: {$batch->count()} (limite {$limit}).");

        $this->table(
            ['Lembrete', 'Vidas', 'Boost de XP'],
            $batch->groupBy('sequence')->sortKeys()->map(fn ($group, $sequence) => [
                "{$sequence}º",
                $group->filter(fn ($due) => $service->giftFor($due['user']) === ReengagementEmail::GIFT_LIVES)->count(),
                $group->filter(fn ($due) => $service->giftFor($due['user']) === ReengagementEmail::GIFT_XP_BOOST)->count(),
            ])->values()->all(),
        );

        if ($this->option('dry-run')) {
            $this->warn('Simulação: nenhum e-mail foi enviado.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($batch as $due) {
            try {
                $service->send($due['user'], $due['sequence'], $due['inactive_days']);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Falhou para {$due['user']->email}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Enviados: {$sent}. Falhas: {$failed}.");

        return $failed > 0 && $sent === 0 ? self::FAILURE : self::SUCCESS;
    }
}
