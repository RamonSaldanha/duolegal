import type { DisciplineLevelUp, PlayUserData } from '@/types/legislation-play';

// Mapa beta (/beta/play): trechos de uma lei, cada um com quadrados de várias fases.

export interface BetaGroupPhase {
    id: number;
    is_complete: boolean;
    is_current: boolean;
}

// Desafio da etapa: fase paralela ao fim de cada etapa, com os resumos e as questões de
// todos os dispositivos da trilha até ali.
export interface BetaCheckpoint {
    // Uuid do último bloco da etapa; nulo só numa etapa sem blocos
    key: string | null;
    // locked: faltam fases da etapa · empty: sem material ainda · available · complete
    state: 'locked' | 'empty' | 'available' | 'complete';
    total: number;
    completed: number;
    questions: number;
    // Não assinante: vê a fase e recebe o convite para assinar
    premium_required: boolean;
}

export interface BetaGroup {
    id: number;
    state: 'complete' | 'current' | 'locked';
    completed_phases: number;
    total_phases: number;
    current_phase_id: number | null;
    phases: BetaGroupPhase[];
}

export interface BetaSection {
    number: number;
    legislation_title: string;
    completed_phases: number;
    total_phases: number;
    is_current: boolean;
    groups: BetaGroup[];
    checkpoint: BetaCheckpoint;
}

export interface BetaFailResponse {
    lost_life: boolean;
    lives: number;
    redirect_url: string | null;
}

export interface BetaFinishResponse {
    xp_gained: number;
    discipline_level_up: DisciplineLevelUp | null;
    user: PlayUserData;
}
