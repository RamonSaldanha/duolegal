<?php

namespace App\Console\Commands;

use App\Models\Discipline;
use App\Services\DisciplineStarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConvertDisciplinesToStars extends Command
{
    protected $signature = 'disciplines:convert-to-stars
        {--force : Grava as mudanças}
        {--recalculate : Recalcula as metas já fixadas (quem já tem estrelas não as perde)}';

    protected $description = 'Fixa as metas de estrelas por disciplina, distribui o XP antigo por área e registra as estrelas de cada usuário.';

    /** Nome do conteúdo antigo => título da legislação, quando divergem além de caixa e acentos. */
    private const LAW_ALIASES = [
        'lei de execucao penal' => 'lei de execucoes penais',
    ];

    public function handle(DisciplineStarService $stars): int
    {
        $apply = (bool) $this->option('force');

        if (! $apply) {
            $this->warn('Modo SIMULAÇÃO: nada será gravado. Use --force para aplicar.');
        }

        $disciplines = Discipline::orderBy('name')->get();
        $potentials = $stars->potentials();
        [$credits, $unmatched] = $this->legacyCredits();

        if ($unmatched !== []) {
            $this->warn('Conteúdo antigo sem disciplina (o XP dele não vira crédito): '.implode(', ', $unmatched));
        }

        $previous = $disciplines->mapWithKeys(fn (Discipline $d) => [$d->id => $d->star_thresholds ?? []])->all();
        $thresholds = $disciplines->mapWithKeys(function (Discipline $d) use ($stars, $potentials) {
            $keep = $d->star_thresholds !== null && ! $this->option('recalculate');

            return [$d->id => $keep ? $d->star_thresholds : $stars->thresholdsFromPotential($potentials[$d->id] ?? 0)];
        })->all();

        $totals = $this->totals($credits);
        $marks = $this->marks($totals, $previous, $thresholds, $stars);

        $this->report($disciplines, $potentials, $thresholds, $marks);
        $this->info(sprintf('Usuários com crédito de XP antigo: %d', count(array_unique(array_column($credits, 'user_id')))));

        if (! $apply) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($disciplines, $potentials, $thresholds, $credits, $marks) {
            foreach ($disciplines as $discipline) {
                if (($potentials[$discipline->id] ?? 0) > 0) {
                    $discipline->update(['star_thresholds' => $thresholds[$discipline->id]]);
                }
            }

            $now = now();
            foreach (array_chunk($credits, 500) as $chunk) {
                DB::table('discipline_xp_credits')->insertOrIgnore(
                    array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $chunk)
                );
            }

            foreach (array_chunk($marks, 500) as $chunk) {
                DB::table('user_discipline_stars')->upsert(
                    array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $chunk),
                    ['user_id', 'discipline_id'],
                    ['stars', 'updated_at']
                );
            }
        });

        $this->info('Conversão gravada.');

        return self::SUCCESS;
    }

    /**
     * Divide o XP da origem "migration" entre as áreas, na proporção dos artigos concluídos em
     * cada lei do jogo antigo. As legislações não apontam para legal_references, daí o vínculo
     * pelo nome. Quem já tem crédito fica como está, então rodar de novo não muda nada.
     *
     * @return array{0: array<int, array{user_id: int, discipline_id: int, xp: int}>, 1: array<int, string>}
     */
    private function legacyCredits(): array
    {
        $lawDisciplines = [];
        $links = DB::table('legislations as l')
            ->join('discipline_legislation as dl', 'dl.legislation_id', '=', 'l.id')
            ->get(['l.title', 'dl.discipline_id']);
        foreach ($links as $link) {
            $lawDisciplines[$this->normalizeLawName($link->title)][] = $link->discipline_id;
        }

        $referenceDisciplines = [];
        $unmatched = [];
        foreach (DB::table('legal_references')->pluck('name', 'id') as $id => $name) {
            $disciplineIds = $lawDisciplines[$this->normalizeLawName($name)] ?? null;
            if ($disciplineIds === null) {
                $unmatched[] = $name;
            } else {
                $referenceDisciplines[$id] = $disciplineIds;
            }
        }

        $migrated = DB::table('xp_transactions')
            ->where('source_type', 'migration')
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $completed = DB::table('user_progress as p')
            ->join('law_articles as a', 'a.id', '=', 'p.law_article_id')
            ->where('p.is_completed', true)
            ->selectRaw('p.user_id, a.legal_reference_id, COUNT(*) as articles')
            ->groupBy('p.user_id', 'a.legal_reference_id')
            ->get()
            ->groupBy('user_id');

        $existing = DB::table('discipline_xp_credits')->get(['user_id', 'discipline_id'])
            ->mapWithKeys(fn ($row) => ["{$row->user_id}:{$row->discipline_id}" => true]);

        $credits = [];
        foreach ($completed as $userId => $laws) {
            if (! isset($migrated[$userId])) {
                continue;
            }

            $allArticles = $laws->sum('articles');
            $byDiscipline = [];
            foreach ($laws as $law) {
                foreach ($referenceDisciplines[$law->legal_reference_id] ?? [] as $disciplineId) {
                    $byDiscipline[$disciplineId] = ($byDiscipline[$disciplineId] ?? 0) + $migrated[$userId] * $law->articles / $allArticles;
                }
            }

            foreach ($byDiscipline as $disciplineId => $xp) {
                if ((int) $xp > 0 && ! isset($existing["{$userId}:{$disciplineId}"])) {
                    $credits[] = ['user_id' => (int) $userId, 'discipline_id' => (int) $disciplineId, 'xp' => (int) $xp];
                }
            }
        }

        return [$credits, $unmatched];
    }

    private function normalizeLawName(string $name): string
    {
        $normalized = trim((string) preg_replace('/\(.*?\)/', '', Str::lower(Str::ascii($name))));

        return self::LAW_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * @param  array<int, array{user_id: int, discipline_id: int, xp: int}>  $credits
     * @return array<string, array{user_id: int, discipline_id: int, xp: int}>
     */
    private function totals(array $credits): array
    {
        $totals = [];
        $add = function (int $userId, int $disciplineId, int $xp) use (&$totals) {
            $key = "{$userId}:{$disciplineId}";
            $totals[$key] ??= ['user_id' => $userId, 'discipline_id' => $disciplineId, 'xp' => 0];
            $totals[$key]['xp'] += $xp;
        };

        $earned = DB::table('xp_transactions as x')
            ->join('discipline_legislation as dl', 'dl.legislation_id', '=', 'x.legislation_id')
            ->selectRaw('x.user_id, dl.discipline_id, SUM(x.amount) as total')
            ->groupBy('x.user_id', 'dl.discipline_id')
            ->get();
        foreach ($earned as $row) {
            $add((int) $row->user_id, (int) $row->discipline_id, (int) $row->total);
        }

        foreach (DB::table('discipline_xp_credits')->get() as $row) {
            $add((int) $row->user_id, (int) $row->discipline_id, (int) $row->xp);
        }

        foreach ($credits as $credit) {
            $add($credit['user_id'], $credit['discipline_id'], $credit['xp']);
        }

        return $totals;
    }

    /**
     * Estrelas a registrar: o maior valor entre o já registrado, as metas atuais e as novas.
     *
     * @return array<int, array{user_id: int, discipline_id: int, stars: int}>
     */
    private function marks(array $totals, array $previous, array $thresholds, DisciplineStarService $stars): array
    {
        $current = DB::table('user_discipline_stars')->get()
            ->mapWithKeys(fn ($row) => ["{$row->user_id}:{$row->discipline_id}" => (int) $row->stars]);

        $marks = [];
        foreach ($totals as $key => $total) {
            $disciplineId = $total['discipline_id'];
            $value = max(
                $current[$key] ?? 0,
                $previous[$disciplineId] === [] ? 0 : $stars->starsForXp($total['xp'], $previous[$disciplineId]),
                $stars->starsForXp($total['xp'], $thresholds[$disciplineId])
            );

            if ($value > 0) {
                $marks[] = ['user_id' => $total['user_id'], 'discipline_id' => $disciplineId, 'stars' => $value];
            }
        }

        return $marks;
    }

    private function report($disciplines, array $potentials, array $thresholds, array $marks): void
    {
        $this->table(
            ['Disciplina', 'XP potencial', '2', '3', '4', '5', 'Usuários 1/2/3/4/5'],
            $disciplines->map(function (Discipline $d) use ($potentials, $thresholds, $marks) {
                $count = array_fill(1, DisciplineStarService::MAX_STARS, 0);
                foreach ($marks as $mark) {
                    if ($mark['discipline_id'] === $d->id) {
                        $count[$mark['stars']]++;
                    }
                }

                return array_merge(
                    [$d->name, $potentials[$d->id] ?? 0],
                    array_pad($thresholds[$d->id], 4, '-'),
                    [implode('/', $count)]
                );
            })->all()
        );
    }
}
