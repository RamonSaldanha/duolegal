<?php

namespace App\Services;

use App\Mail\ReengagementMail;
use App\Models\ReengagementEmail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Lembretes por e-mail para quem parou de jogar, cada um com um presente:
 * 5 vidas para quem joga de graça, 50% a mais de XP por 3 dias para assinante.
 *
 * O intervalo cresce de 15 em 15 dias. O 1º lembrete sai depois de 15 dias
 * parado, o 2º 30 dias depois do 1º, o 3º 45 dias depois do 2º, e assim por
 * diante. Voltar a jogar zera a sequência.
 *
 * Cada execução manda no máximo um lembrete por pessoa e sempre o próximo da
 * sequência, nunca os atrasados. Quem nunca recebeu nada (como as contas que
 * já existiam quando isto entrou) ganha só o 1º.
 *
 * Nada aqui muda o jogo nem a API do app: o presente só soma vidas ou liga o
 * boost, que o servidor aplica no XP de qualquer cliente.
 */
class ReengagementService
{
    public const STEP_DAYS = 15;

    public const GIFT_LIVES = 5;

    public const BOOST_DAYS = 3;

    public const BOOST_MULTIPLIER = 1.5;

    /**
     * Quem está na hora de receber o próximo lembrete, de quem jogou mais
     * recentemente para quem sumiu há mais tempo (os primeiros voltam mais).
     *
     * @return Collection<int, array{user: User, sequence: int, inactive_days: int}>
     */
    public function dueUsers(?Carbon $now = null): Collection
    {
        $now ??= now();

        $users = User::query()
            ->withLastPlayed()
            ->whereNull('email_opt_out_at')
            ->where('created_at', '<=', $now->copy()->subDays(self::STEP_DAYS))
            ->with(['subscriptions', 'reengagementEmails' => fn ($q) => $q->orderBy('sent_at')])
            ->get();

        return $users
            ->map(function (User $user) use ($now) {
                $lastActivity = $this->lastActivity($user);
                $sent = $user->reengagementEmails->filter(fn (ReengagementEmail $e) => $e->sent_at->gt($lastActivity));
                $sequence = $sent->count() + 1;
                $from = $sent->isEmpty() ? $lastActivity : $sent->last()->sent_at;

                if ($from->copy()->addDays(self::STEP_DAYS * $sequence)->gt($now)) {
                    return null;
                }

                return [
                    'user' => $user,
                    'sequence' => $sequence,
                    'inactive_days' => (int) $lastActivity->diffInDays($now),
                    'last_activity' => $lastActivity,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $due) => $due['last_activity']->getTimestamp())
            ->map(fn (array $due) => collect($due)->except('last_activity')->all())
            ->values();
    }

    /** Última jogada ou, para quem nunca jogou, o cadastro. Pede User::withLastPlayed(). */
    public function lastActivity(User $user): Carbon
    {
        return $user->lastPlayedAt() ?? $user->created_at->copy();
    }

    public function giftFor(User $user): string
    {
        return $user->hasActiveSubscription() ? ReengagementEmail::GIFT_XP_BOOST : ReengagementEmail::GIFT_LIVES;
    }

    /**
     * Registra e manda o lembrete. Se o envio falhar, o registro sai junto, para
     * a pessoa entrar de novo na próxima execução.
     */
    public function send(User $user, int $sequence, int $inactiveDays): ReengagementEmail
    {
        $email = $user->reengagementEmails()->create([
            'sequence' => $sequence,
            'gift' => $this->giftFor($user),
            'inactive_days' => $inactiveDays,
            'sent_at' => now(),
        ]);

        try {
            Mail::to($user)->send(new ReengagementMail($email));
        } catch (\Throwable $e) {
            $email->delete();

            throw $e;
        }

        return $email;
    }

    /**
     * Situação do presente para a página de resgate: `available`, `claimed` ou
     * `expired` (só o lembrete mais novo vale, para os antigos não somarem).
     */
    public function giftStatus(ReengagementEmail $email): string
    {
        if ($email->claimed_at !== null) {
            return 'claimed';
        }

        $newer = ReengagementEmail::where('user_id', $email->user_id)
            ->where('id', '>', $email->id)
            ->exists();

        return $newer ? 'expired' : 'available';
    }

    /** Abre o presente. Devolve falso se ele já foi aberto ou expirou. */
    public function claim(ReengagementEmail $email): bool
    {
        return DB::transaction(function () use ($email) {
            $email = ReengagementEmail::whereKey($email->id)->lockForUpdate()->first();

            if ($this->giftStatus($email) !== 'available') {
                return false;
            }

            $user = $email->user;

            if ($email->gift === ReengagementEmail::GIFT_XP_BOOST) {
                // Se ainda houver boost ligado, os 3 dias somam no fim dele.
                $start = $user->hasXpBoost() ? $user->xp_boost_until : now();
                $user->xp_boost_until = $start->copy()->addDays(self::BOOST_DAYS);
                $user->save();
            } else {
                $user->incrementLife(self::GIFT_LIVES);
            }

            $email->claimed_at = now();
            $email->save();

            return true;
        });
    }
}
