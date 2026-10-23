import { AREAS, GOALS, MINUTES } from '@/components/onboarding/quiz';

/** Rótulos das respostas do onboarding, do jeito que o quiz mostra. */
export const goalLabel = (goal: string | null): string | null => GOALS.find((g) => g.value === goal)?.title ?? null;

export const areaLabel = (area: string | null): string | null => AREAS.find((a) => a.value === area)?.title ?? null;

export const minutesLabel = (minutes: number | null): string | null => MINUTES.find((m) => m.value === minutes)?.title ?? null;

const DAY = 24 * 60 * 60 * 1000;

/** Dias inteiros desde a data (0 = hoje). */
export const daysSince = (iso: string): number => Math.floor((Date.now() - new Date(iso).getTime()) / DAY);

/** "hoje", "ontem", "há 5 dias", "há 3 meses", "há mais de 1 ano". */
export const timeAgo = (iso: string | null): string | null => {
    if (!iso) {
        return null;
    }
    const days = daysSince(iso);
    if (days <= 0) return 'hoje';
    if (days === 1) return 'ontem';
    if (days < 60) return `há ${days} dias`;
    if (days < 365) return `há ${Math.floor(days / 30)} meses`;
    return 'há mais de 1 ano';
};

export const formatDate = (iso: string | null, withTime = false): string => {
    if (!iso) {
        return '';
    }
    const date = new Date(iso);
    const day = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    return withTime ? `${day} às ${date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}` : day;
};

export const formatNumber = (value: number): string => value.toLocaleString('pt-BR');
