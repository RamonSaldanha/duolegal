import type { DisciplineLevelUp, PlayUserData } from '@/types/legislation-play';

// Mapa beta (/beta/play): trechos de uma lei, cada um com quadrados de várias fases.

export interface BetaGroupPhase {
    id: number;
    is_complete: boolean;
    is_current: boolean;
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
