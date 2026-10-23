<?php

namespace App\Console\Commands;

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\UserSegmentProgress;
use App\Services\LacunaAutoSelectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimplifyLacunas extends Command
{
    protected $signature = 'lacunas:simplify
        {--legislation=* : IDs ou UUIDs das legislações (padrão: todas)}
        {--dry-run : Apenas simula, sem gravar}
        {--force : Grava as mudanças}
        {--restore : Desfaz a facilitação (reativa tudo e remove o que ela criou)}';

    protected $description = 'Deixa os blocos mais fáceis (poucas lacunas conforme o tamanho, poucas palavras falsas) ocultando lacunas, sem apagar nada nem tocar no progresso.';

    private const CHUNK = 200;

    public function handle(LacunaAutoSelectionService $service): int
    {
        $apply = $this->option('force') && ! $this->option('dry-run');

        if (! $apply) {
            $this->warn('Modo SIMULAÇÃO: nada será gravado. Use --force para aplicar.');
        }

        $legislations = $this->legislations();
        if ($legislations->isEmpty()) {
            $this->error('Nenhuma legislação encontrada.');

            return self::FAILURE;
        }

        $progressBefore = UserSegmentProgress::count();

        $result = $this->option('restore')
            ? $this->restore($legislations, $apply)
            : $this->simplify($legislations, $service, $apply);

        if ($apply && UserSegmentProgress::count() !== $progressBefore) {
            $this->error('O total de registros de progresso mudou durante a execução. Verifique!');

            return self::FAILURE;
        }

        $this->line('');
        $this->info($apply ? 'Concluído.' : 'Simulação concluída. Nada foi gravado.');
        $this->line("  Registros de progresso: {$progressBefore} (inalterados)");

        return $result;
    }

    private function simplify($legislations, LacunaAutoSelectionService $service, bool $apply): int
    {
        foreach ($legislations as $legislation) {
            $this->line('');
            $this->info("{$legislation->title} (#{$legislation->id})");

            $familiarity = $service->familiarity(
                $legislation->segments()->where('is_block', true)->pluck('original_text')->lazy()
            );

            $before = [];
            $after = [];
            $created = 0;
            $examples = [];

            $legislation->segments()
                ->where('is_block', true)
                ->with('lacunas')
                ->chunkById(self::CHUNK, function ($segments) use ($service, $familiarity, $apply, &$before, &$after, &$created, &$examples) {
                    $plans = [];
                    foreach ($segments as $segment) {
                        $activeGaps = $segment->lacunas->where('is_correct', true)->where('is_active', true)->count();
                        $before[$this->bucket($activeGaps)] = ($before[$this->bucket($activeGaps)] ?? 0) + 1;

                        $plan = $service->planSimplification($segment, $familiarity);
                        $after[$this->bucket($plan['gaps'])] = ($after[$this->bucket($plan['gaps'])] ?? 0) + 1;
                        $created += count($plan['create']);

                        if ($activeGaps >= 5 && count($examples) < 5) {
                            $examples[] = [
                                $segment->article_reference ?? '-',
                                $activeGaps,
                                implode(', ', $plan['words']),
                            ];
                        }

                        $plans[] = [$segment, $plan];
                    }

                    if ($apply) {
                        DB::transaction(function () use ($service, $plans) {
                            foreach ($plans as [$segment, $plan]) {
                                $service->applySimplification($segment, $plan);
                            }
                        });
                    }
                });

            ksort($before);
            ksort($after);
            $this->table(
                ['Lacunas por bloco', 'Antes', 'Depois'],
                collect(array_unique(array_merge(array_keys($before), array_keys($after))))
                    ->sort()
                    ->map(fn ($k) => [$k === 6 ? '6+' : $k, $before[$k] ?? 0, $after[$k] ?? 0])
                    ->values()
                    ->all(),
            );
            $this->line("  Lacunas/palavras falsas criadas: {$created}");

            if ($examples) {
                $this->table(['Artigo', 'Lacunas antes', 'Lacunas escolhidas'], $examples);
            }
        }

        return self::SUCCESS;
    }

    private function restore($legislations, bool $apply): int
    {
        $segmentIds = LegislationSegment::whereIn('legislation_id', $legislations->pluck('id'))->pluck('id');

        $toDelete = SegmentLacuna::whereIn('legislation_segment_id', $segmentIds)->where('auto_added', true);
        $toReactivate = SegmentLacuna::whereIn('legislation_segment_id', $segmentIds)
            ->where('auto_added', false)
            ->where('is_active', false);

        $this->line('  Linhas criadas pela facilitação a remover: '.(clone $toDelete)->count());
        $this->line('  Lacunas ocultas a reativar: '.(clone $toReactivate)->count());

        if ($apply) {
            DB::transaction(function () use ($toDelete, $toReactivate) {
                $toDelete->delete();
                $toReactivate->update(['is_active' => true]);
            });
        }

        return self::SUCCESS;
    }

    private function legislations()
    {
        $filters = $this->option('legislation');

        return Legislation::query()
            ->when($filters, fn ($q) => $q->where(fn ($q) => $q->whereIn('id', $filters)->orWhereIn('uuid', $filters)))
            ->orderBy('id')
            ->get();
    }

    private function bucket(int $gaps): int
    {
        return min($gaps, 6);
    }
}
