<?php

namespace App\Console\Commands;

use App\Models\SegmentLacuna;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RemoveParagrafoLacunas extends Command
{
    protected $signature = 'lacunas:remove-paragrafo {--dry-run : Simulate without touching the database} {--force : Actually apply the changes}';

    protected $description = 'Remove ALL lacunas with word="Parágrafo" (exact match) from segment_lacunas. Segments may end up with zero lacunas — that is allowed (read-only block).';

    public function handle(): int
    {
        $apply = $this->option('force') && ! $this->option('dry-run');

        if (! $apply) {
            $this->warn('Running in DRY-RUN mode. No changes will be persisted. Use --force to apply.');
        } else {
            $this->info('Running in APPLY mode. Changes will be persisted inside a transaction.');
        }

        $lacunas = SegmentLacuna::query()
            ->where('word', 'Parágrafo')
            ->with('segment:id,uuid,article_reference,original_text')
            ->get();

        $total = $lacunas->count();
        $this->info("Found {$total} lacuna(s) with word='Parágrafo'.");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $distractorCount = $lacunas->where('is_correct', false)->count();
        $correctCount = $lacunas->where('is_correct', true)->count();

        $affectedSegmentIds = $lacunas->pluck('legislation_segment_id')->unique()->values();

        $correctCountsBySegment = SegmentLacuna::query()
            ->whereIn('legislation_segment_id', $affectedSegmentIds)
            ->where('is_correct', true)
            ->selectRaw('legislation_segment_id, COUNT(*) as total')
            ->groupBy('legislation_segment_id')
            ->pluck('total', 'legislation_segment_id');

        $paragrafoCorrectBySegment = $lacunas
            ->where('is_correct', true)
            ->groupBy('legislation_segment_id')
            ->map->count();

        $emptiedSegmentLacunas = [];
        foreach ($lacunas as $lacuna) {
            if (! $lacuna->is_correct) {
                continue;
            }
            $segmentId = $lacuna->legislation_segment_id;
            $totalCorrect = (int) ($correctCountsBySegment[$segmentId] ?? 0);
            $paragrafoCorrect = (int) ($paragrafoCorrectBySegment[$segmentId] ?? 0);

            if ($totalCorrect - $paragrafoCorrect === 0) {
                $emptiedSegmentLacunas[$segmentId] = $lacuna;
            }
        }

        $this->line('');
        $this->info('Breakdown:');
        $this->line('  Distractors (is_correct=false) to delete: '.$distractorCount);
        $this->line('  Correct to delete: '.$correctCount);
        $this->line('  Segments that will end with ZERO correct lacunas: '.count($emptiedSegmentLacunas));

        $this->printEmptied($emptiedSegmentLacunas);
        $this->writeEmptiedCsv($emptiedSegmentLacunas, $apply);

        if (! $apply) {
            $this->line('');
            $this->info('Dry-run complete. No changes applied.');
            return self::SUCCESS;
        }

        $deletedIds = $lacunas->pluck('id')->all();

        DB::transaction(function () use ($deletedIds, $affectedSegmentIds) {
            SegmentLacuna::whereIn('id', $deletedIds)->delete();

            foreach ($affectedSegmentIds as $segmentId) {
                $this->renumberGapOrder((int) $segmentId);
            }
        });

        $this->line('');
        $this->info('Done.');
        $this->line('  Deleted lacunas: '.count($deletedIds));
        $this->line('  Segments touched: '.$affectedSegmentIds->count());
        $this->line('  Segments that ended with ZERO correct lacunas: '.count($emptiedSegmentLacunas));

        return self::SUCCESS;
    }

    private function renumberGapOrder(int $segmentId): void
    {
        $corrects = SegmentLacuna::query()
            ->where('legislation_segment_id', $segmentId)
            ->where('is_correct', true)
            ->orderBy('gap_order')
            ->orderBy('id')
            ->get();

        $order = 1;
        foreach ($corrects as $lacuna) {
            if ($lacuna->gap_order !== $order) {
                $lacuna->gap_order = $order;
                $lacuna->save();
            }
            $order++;
        }
    }

    /**
     * @param  array<int, SegmentLacuna>  $emptied
     */
    private function printEmptied(array $emptied): void
    {
        if (empty($emptied)) {
            return;
        }

        $this->line('');
        $this->warn('Segments that will end with ZERO correct lacunas (block becomes read-only):');

        $rows = [];
        foreach ($emptied as $lacuna) {
            $segment = $lacuna->segment;
            $rows[] = [
                $segment?->uuid ?? '(missing segment)',
                $segment?->article_reference ?? '-',
                $this->truncate((string) ($segment?->original_text ?? ''), 80),
            ];
        }

        $this->table(['segment_uuid', 'article_reference', 'original_text'], $rows);
    }

    /**
     * @param  array<int, SegmentLacuna>  $emptied
     */
    private function writeEmptiedCsv(array $emptied, bool $apply): void
    {
        if (empty($emptied)) {
            return;
        }

        $dir = storage_path('logs');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $suffix = $apply ? 'force' : 'dryrun';
        $timestamp = now()->format('Ymd-His');
        $path = $dir.DIRECTORY_SEPARATOR."paragrafo-emptied-segments-{$timestamp}-{$suffix}.csv";

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("Failed to open log file for writing: {$path}");
            return;
        }

        fputcsv($handle, ['segment_uuid', 'article_reference', 'legislation_segment_id', 'original_text']);

        foreach ($emptied as $lacuna) {
            $segment = $lacuna->segment;
            fputcsv($handle, [
                $segment?->uuid ?? '',
                $segment?->article_reference ?? '',
                $lacuna->legislation_segment_id,
                (string) ($segment?->original_text ?? ''),
            ]);
        }

        fclose($handle);

        $this->line('');
        $this->info("Emptied segments logged to: {$path}");
    }

    private function truncate(string $text, int $length): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 1).'…';
    }
}
