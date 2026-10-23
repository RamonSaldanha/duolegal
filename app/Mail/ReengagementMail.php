<?php

namespace App\Mail;

use App\Models\ReengagementEmail;
use App\Models\User;
use App\Services\ReengagementService;
use App\Services\StreakService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * O lembrete com presente para quem parou de jogar. O texto muda conforme a
 * sequência (1º, 2º, 3º em diante), o presente e o objetivo do onboarding.
 *
 * Regras do texto: nada de travessão (intercalação vai entre vírgulas,
 * parênteses ou vira outra frase) e nada de emoji.
 */
class ReengagementMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?array $copy = null;

    public function __construct(public ReengagementEmail $email) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->copy()['subject']);
    }

    /** Descadastro em um clique direto no Gmail e no Outlook (RFC 8058). */
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.self::unsubscribeUrl($this->email->user).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reengagement',
            text: 'emails.reengagement-text',
            with: [
                'copy' => $this->copy(),
                'gift' => $this->email->gift,
                'giftUrl' => route('gift.show', $this->email->uuid),
                'logoImage' => asset('img/email/logo.png'),
                'giftImage' => asset($this->email->gift === ReengagementEmail::GIFT_XP_BOOST ? 'img/email/gift-xp.png' : 'img/email/gift-lives.png'),
                'unsubscribeUrl' => self::unsubscribeUrl($this->email->user),
                'stats' => $this->stats(),
                'userEmail' => $this->email->user->email,
            ],
        );
    }

    /**
     * Link de descadastro. Assinatura relativa: não quebra se o servidor enxergar
     * http atrás de um proxy enquanto o link foi gerado com https.
     */
    public static function unsubscribeUrl(User $user): string
    {
        return url(URL::signedRoute('emails.unsubscribe', ['user' => $user->id], absolute: false));
    }

    /**
     * @return array{subject: string, preheader: string, title: string, intro: string, gift_title: string, gift_text: string, gift_note: ?string, goal: ?string, cta: string, cta_note: string, ps: string}
     */
    public function copy(): array
    {
        if ($this->copy !== null) {
            return $this->copy;
        }

        $user = $this->email->user;
        $name = self::firstName($user->name);
        $sequence = $this->email->sequence;
        $boost = $this->email->gift === ReengagementEmail::GIFT_XP_BOOST;
        $neverPlayed = $sequence === 1 && $this->neverPlayed($user);
        $away = self::timeAway($this->email->inactive_days);
        $lives = ReengagementService::GIFT_LIVES;
        $boostDays = ReengagementService::BOOST_DAYS;
        $boostPercent = (int) round((ReengagementService::BOOST_MULTIPLIER - 1) * 100);

        [$subject, $title, $intro] = match (true) {
            $neverPlayed => [
                "{$name}, sua primeira partida tá te esperando",
                'Seu cadastro tá no modo avião',
                'Você criou a conta e sumiu antes da primeira lacuna. Clássico de quem paga a academia e só aparece no primeiro dia, né? Sem julgamentos. Uma partida leva uns 2 minutinhos e já dá pra sentir o gostinho.',
            ],
            $sequence === 1 => [
                $boost ? "{$name}, seu XP tá pedindo um boost" : "{$name}, chegou presente pra você",
                "Cadê você, {$name}?",
                "Faz {$away} que você não aparece por aqui. Os artigos ficaram parados te esperando, tipo aquele áudio de 3 minutos que ninguém tem coragem de ouvir. Bora destravar?",
            ],
            $sequence === 2 => [
                "Sumiu, hein, {$name}? Trouxe outro presente",
                "Faz {$away}. A gente contou.",
                'A gente sabe que a vida tá corrida: faculdade, estágio, prova, boleto, crush que visualiza e não responde. Mas 5 minutinhos por dia já fazem a lei grudar na cabeça. Palavra de quem já viu gente decorar o art. 5º inteiro.',
            ],
            default => [
                "{$name}, a gente não desiste de você",
                'Não é cobrança. É saudade mesmo.',
                'Não vamos mandar indireta no story. Vamos mandar presente, porque a gente acredita no seu potencial (e porque o ranking fica meio sem graça sem você).',
            ],
        };

        return $this->copy = [
            'subject' => $subject,
            'preheader' => $boost
                ? "{$boostPercent}% a mais de XP por {$boostDays} dias. O ranking que lute."
                : "Tem {$lives} vidas novinhas aqui. É só abrir e voltar pro jogo.",
            'title' => $title,
            'intro' => $intro,
            'gift_title' => $boost ? "XP turbinado por {$boostDays} dias" : "+{$lives} vidas",
            'gift_text' => $boost
                ? "Você é Premium, então vida infinita você já tem. O presente é outro: cada lacuna certa rende {$boostPercent}% a mais de XP. O ranking que lute."
                : 'Pra você errar sem medo e aprender no processo. Errou? Tenta de novo. É assim que a lei gruda.',
            'gift_note' => $boost ? "Os {$boostDays} dias começam a contar quando você abrir o presente." : null,
            'goal' => self::goalLine($user->onboarding_answers['goal'] ?? null),
            'cta' => 'Abrir meu presente',
            'cta_note' => 'Leva 1 clique. Sem pegadinha e sem letra miúda (essa fica só na lei mesmo).',
            'ps' => $sequence >= 3
                ? 'PS: se quiser dar um tempo da gente, tudo bem. Tem um link aqui embaixo pra parar de receber esses e-mails. Sem ressentimentos (tá, só um pouquinho).'
                : 'PS: 5 minutinhos contam. Dá pra jogar até na fila do bandejão.',
        ];
    }

    /**
     * Os números do HUD do jogo, do jeito que a pessoa deixou.
     *
     * @return array{xp: int, lives: ?int, record: int}|null
     */
    private function stats(): ?array
    {
        $user = $this->email->user;
        $xp = $user->xp;

        if ($xp === 0) {
            return null;
        }

        return [
            'xp' => $xp,
            'lives' => $user->hasActiveSubscription() ? null : $user->lives,
            'record' => app(StreakService::class)->longestStreak($user->id),
        ];
    }

    private function neverPlayed(User $user): bool
    {
        return User::withLastPlayed()->whereKey($user->id)->first()?->lastPlayedAt() === null;
    }

    public static function firstName(string $name): string
    {
        $first = strtok(trim($name), " \t") ?: 'você';

        return mb_convert_case(mb_strtolower($first), MB_CASE_TITLE);
    }

    /** "15 dias", "uns 3 meses", "mais de um ano". */
    public static function timeAway(int $days): string
    {
        return match (true) {
            $days < 60 => "{$days} dias",
            $days < 365 => 'uns '.intdiv($days, 30).' meses',
            default => 'mais de um ano',
        };
    }

    public static function goalLine(?string $goal): ?string
    {
        return match ($goal) {
            'oab' => 'E não esquece do seu objetivo: passar na OAB. A aprovação não cai do céu, mas cai da lei seca.',
            'concurso' => 'E o concurso, hein? A vaga não vai ficar te esperando sentada.',
            'faculdade' => 'A prova da facul tá chegando, e decorar artigo na véspera é esporte radical.',
            'profissao' => 'Profissional atualizado não passa sufoco em audiência. Bora manter a lei fresquinha na cabeça?',
            'pessoal' => 'Conhecer seus direitos é tipo ter superpoder. E todo superpoder precisa de treino.',
            default => null,
        };
    }
}
