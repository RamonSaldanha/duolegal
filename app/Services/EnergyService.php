<?php

namespace App\Services;

use App\Models\EnergyTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Energia do mapa novo (app e /beta/play), no lugar das vidas. Os números ficam em
 * config/energy.php.
 *
 * - Fase nova: precisa de `phase.open` para abrir; cada tentativa custa (a errada custa mais)
 *   e a cada `combo_every` blocos certos seguidos volta um tanto. Se a energia acaba no meio,
 *   a pessoa sai e perde a fase (o finish é recusado).
 * - Revisão: abre mesmo sem energia, custa só o erro e o combo dela tem teto por dia.
 * - Desafio do quadrado: cada questão nova custa e também tem combo.
 * - Dia da prova: não custa; rende energia e pode passar da barra cheia (`super_max`).
 * - O tempo devolve +1 a cada `regen_minutes`, até a barra cheia. A conta é feita na leitura,
 *   a partir de `energy_updated_at` (não há cron).
 * - Quem assina tem energia infinita: nada é gasto nem ganho.
 *
 * A fase ou o desafio em jogo fica em `users.energy_run` (a sequência do combo e se a energia
 * acabou no meio).
 */
class EnergyService
{
    public function max(): int
    {
        return (int) config('energy.max');
    }

    public function infinite(User $user): bool
    {
        return $user->hasActiveSubscription();
    }

    /** A energia agora, já com a recarga que o tempo deu (sem gravar). */
    public function current(User $user): int
    {
        return $this->regenerated($user)[0];
    }

    /** Tem pelo menos `$need` (ou energia infinita). */
    public function has(User $user, int $need): bool
    {
        return $this->infinite($user) || $this->current($user) >= $need;
    }

    /** Quando vem o próximo +1, ou null se a barra está cheia (ou é infinita). */
    public function nextAt(User $user): ?CarbonInterface
    {
        [$energy, $since] = $this->regenerated($user);

        if ($energy >= $this->max() || $since === null || $this->infinite($user)) {
            return null;
        }

        return $since->copy()->addMinutes((int) config('energy.regen_minutes'));
    }

    /**
     * A energia para as telas.
     *
     * @return array{value: int, max: int, next_at: string|null, infinite: bool}
     */
    public function payload(User $user): array
    {
        return [
            'value' => $this->current($user),
            'max' => $this->max(),
            'next_at' => $this->nextAt($user)?->toIso8601String(),
            'infinite' => $this->infinite($user),
        ];
    }

    /**
     * Soma (ou tira, se negativo) energia e escreve no livro. O gasto para em 0; o ganho para
     * em `$cap` (a barra cheia, por padrão), sem nunca tirar o que já passa dele. Quem assina
     * não gasta nem ganha.
     *
     * @return int o quanto a energia mudou de fato
     */
    public function apply(User $user, int $delta, string $reason, ?int $cap = null): int
    {
        if ($delta === 0 || $this->infinite($user)) {
            return 0;
        }

        return DB::transaction(function () use ($user, $delta, $reason, $cap) {
            $row = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            [$energy, $since] = $this->regenerated($row);

            $new = $delta < 0
                ? max(0, $energy + $delta)
                : max($energy, min($cap ?? $this->max(), $energy + $delta));

            // A recarga conta da hora em que a barra deixou de estar cheia
            if ($new >= $this->max()) {
                $since = null;
            } elseif ($energy >= $this->max() || $since === null) {
                $since = now();
            }

            $this->store($row, $user, ['energy' => $new, 'energy_updated_at' => $since]);

            if ($new !== $energy) {
                EnergyTransaction::create(['user_id' => $user->id, 'amount' => $new - $energy, 'reason' => $reason]);
            }

            return $new - $energy;
        });
    }

    /** Completa a energia até `$level`, se estiver abaixo. */
    public function fillTo(User $user, int $level, string $reason): int
    {
        $missing = $level - $this->current($user);

        return $missing > 0 ? $this->apply($user, $missing, $reason, $level) : 0;
    }

    /** Começa a fase ou o desafio em jogo (a sequência do combo zera). */
    public function startRun(User $user, array $run): void
    {
        $this->store(null, $user, ['energy_run' => $run]);
    }

    public function clearRun(User $user): void
    {
        $this->store(null, $user, ['energy_run' => null]);
    }

    /**
     * Uma tentativa de bloco na fase em jogo: cobra, conta a sequência e dá o combo. Na fase
     * nova, se a energia chega a 0 e ainda falta bloco, a pessoa está fora (`out`).
     *
     * @param  array{kind: string, id: int, review: bool, pending: int, corrects: int, streak: int, out: bool}  $run
     * @return array{energy: array<string, mixed>, combo: array{streak: int, gained: int}|null, out_of_energy: bool}
     */
    public function phaseAttempt(User $user, array $run, bool $correct): array
    {
        $review = (bool) $run['review'];
        $rules = config($review ? 'energy.review' : 'energy.phase');

        if (! $run['out']) {
            $this->apply($user, -$rules[$correct ? 'correct' : 'wrong'], match (true) {
                $review => EnergyTransaction::REVIEW,
                $correct => EnergyTransaction::PHASE,
                default => EnergyTransaction::FAIL,
            });
        }

        $combo = null;
        if ($correct) {
            $run['corrects']++;
            $combo = $this->streak($user, $run, $rules, $review ? EnergyTransaction::REVIEW_COMBO : EnergyTransaction::COMBO);
        } else {
            $run['streak'] = 0;
        }

        $run['out'] = $run['out'] || (! $review && ! $this->has($user, 1) && $run['corrects'] < $run['pending']);
        $this->startRun($user, $run);

        return ['energy' => $this->payload($user), 'combo' => $combo, 'out_of_energy' => $run['out']];
    }

    /**
     * Uma questão nova respondida no desafio do quadrado. `$remaining`: ainda falta questão no
     * desafio depois desta (se a energia acabou, a pessoa sai e volta depois de onde parou).
     *
     * @return array{energy: array<string, mixed>, combo: array{streak: int, gained: int}|null, out_of_energy: bool}
     */
    public function challengeAnswer(User $user, string $key, bool $correct, bool $remaining): array
    {
        $rules = config('energy.challenge');
        $run = $user->energy_run;

        if (($run['kind'] ?? null) !== 'challenge' || ($run['id'] ?? null) !== $key) {
            $run = ['kind' => 'challenge', 'id' => $key, 'streak' => 0];
        }

        $this->apply($user, -$rules['question'], EnergyTransaction::CHALLENGE);

        $combo = null;
        if ($correct) {
            $combo = $this->streak($user, $run, $rules, EnergyTransaction::COMBO);
        } else {
            $run['streak'] = 0;
        }

        $this->startRun($user, $run);

        return [
            'energy' => $this->payload($user),
            'combo' => $combo,
            'out_of_energy' => $remaining && ! $this->has($user, 1),
        ];
    }

    /**
     * A energia que uma tentativa do Dia da prova rende: um tanto por questão acertada pela
     * primeira vez e, só na primeira entrega, o combo de acertos seguidos (na ordem em que
     * foram respondidas).
     *
     * @param  array<int, array{correct: bool}>  $answers
     */
    public function examGain(array $answers, int $newlyCorrect, bool $first): int
    {
        $rules = config('energy.exam');
        $gain = $newlyCorrect * $rules['correct'];

        if ($first) {
            $streak = 0;
            foreach ($answers as $given) {
                $streak = $given['correct'] ? $streak + 1 : 0;
                if ($streak > 0 && $streak % $rules['combo_every'] === 0) {
                    $gain += $rules['combo_gain'];
                }
            }
        }

        return $gain;
    }

    /** Credita o Dia da prova (pode passar da barra cheia) e, com nota alta, completa a barra. */
    public function creditExam(User $user, int $gain, bool $fill): int
    {
        $gained = $this->apply($user, $gain, EnergyTransaction::EXAM, (int) config('energy.super_max'));

        return $gained + ($fill ? $this->fillTo($user, $this->max(), EnergyTransaction::EXAM) : 0);
    }

    // ==================== PRIVATE METHODS ====================

    /**
     * Conta mais um acerto na sequência e dá o combo quando ela fecha um múltiplo de
     * `combo_every`. O combo da revisão respeita o teto do dia.
     *
     * @return array{streak: int, gained: int}|null
     */
    private function streak(User $user, array &$run, array $rules, string $reason): ?array
    {
        $run['streak'] = ($run['streak'] ?? 0) + 1;

        if ($run['streak'] % $rules['combo_every'] !== 0 || $this->infinite($user)) {
            return null;
        }

        $gain = $rules['combo_gain'];
        if ($reason === EnergyTransaction::REVIEW_COMBO) {
            $gain = min($gain, $this->reviewComboLeft($user));
        }

        return ['streak' => $run['streak'], 'gained' => $gain > 0 ? $this->apply($user, $gain, $reason) : 0];
    }

    /** Quanto o combo da revisão ainda pode render hoje (dia de Brasília). */
    private function reviewComboLeft(User $user): int
    {
        $today = now('America/Sao_Paulo')->startOfDay()->utc();
        $given = (int) EnergyTransaction::where('user_id', $user->id)
            ->where('reason', EnergyTransaction::REVIEW_COMBO)
            ->where('created_at', '>=', $today)
            ->sum('amount');

        return max(0, (int) config('energy.review.daily_cap') - $given);
    }

    /**
     * A energia com a recarga do tempo, e a partir de quando conta a próxima.
     *
     * @return array{0: int, 1: CarbonInterface|null}
     */
    private function regenerated(User $user): array
    {
        $energy = (int) ($user->energy ?? $this->max());
        $since = $user->energy_updated_at;

        if ($energy >= $this->max() || $since === null) {
            return [$energy, $since];
        }

        $minutes = (int) config('energy.regen_minutes');
        $steps = intdiv(max(0, now()->getTimestamp() - $since->getTimestamp()), $minutes * 60);

        if ($steps === 0) {
            return [$energy, $since];
        }

        $energy = min($this->max(), $energy + $steps);

        return [$energy, $energy >= $this->max() ? null : $since->copy()->addMinutes($steps * $minutes)];
    }

    /** Grava na linha (a travada, se houver) e deixa o model de quem chamou igual. */
    private function store(?User $row, User $user, array $values): void
    {
        ($row ?? $user)->forceFill($values)->save();

        if ($row !== null) {
            $user->forceFill($values)->syncOriginalAttributes(array_keys($values));
        }
    }
}
