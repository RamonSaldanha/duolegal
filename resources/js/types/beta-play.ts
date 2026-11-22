import type { DisciplineLevelUp, PlayUserData } from '@/types/legislation-play';

// Mapa beta (/beta/play): trechos de uma lei, cada um com quadrados de várias fases.

export interface BetaGroupPhase {
    id: number;
    is_complete: boolean;
    is_current: boolean;
}

// Desafio do quadrado: o último passo do quadrado que tem material de estudo, com o resumo e
// as questões dos dispositivos dele. O quadrado seguinte só abre depois dele.
export interface BetaChallenge {
    // Uuid do último bloco do quadrado
    key: string | null;
    // none: quadrado sem material · locked: faltam fases · available · complete
    state: 'none' | 'locked' | 'available' | 'complete';
    // É o passo atual da trilha: o quadrado seguinte espera por ele
    is_current: boolean;
    // Materiais (normalmente um) e quantos já foram feitos
    total: number;
    completed: number;
    questions: number;
}

// Dia da prova: fase paralela ao fim de cada etapa. Prova com tempo, só com as questões dos
// desafios dos quadrados da etapa.
export interface BetaExam {
    // Uuid do último bloco da etapa
    key: string | null;
    // locked: falta concluir a etapa · empty: sem questões · available · complete: já entregou uma prova
    state: 'locked' | 'empty' | 'available' | 'complete';
    questions: number;
    seconds: number;
    xp_per_correct: number;
    best_correct: number;
    // Há uma prova em andamento, com o tempo correndo
    in_progress: boolean;
}

export interface BetaGroup {
    id: number;
    state: 'complete' | 'current' | 'locked';
    completed_phases: number;
    total_phases: number;
    // Passos do quadrado: as fases e, quando há material, o desafio
    completed_steps: number;
    total_steps: number;
    current_phase_id: number | null;
    phases: BetaGroupPhase[];
    challenge: BetaChallenge;
}

export interface BetaSection {
    number: number;
    legislation_title: string;
    completed_phases: number;
    total_phases: number;
    completed_steps: number;
    total_steps: number;
    is_current: boolean;
    groups: BetaGroup[];
    exam: BetaExam;
}

/** Questão do Dia da prova como o jogador recebe: sem gabarito. */
export interface BetaExamQuestion {
    uuid: string;
    board: string;
    type: 'true_false' | 'multiple_choice';
    statement: string;
    options: Record<string, string> | null;
}

export interface BetaExamGiven {
    answer: string;
    is_correct: boolean;
    correct_answer: string;
}

/** Prova em andamento: a ordem sorteada, o que já foi respondido e o tempo que resta. */
export interface BetaExamAttempt {
    seconds_left: number;
    seconds_total: number;
    xp: number;
    // Energia somada na prova até aqui (só é creditada na entrega)
    energy: number;
    questions: BetaExamQuestion[];
    answers: Record<string, BetaExamGiven>;
}

export interface BetaExamReviewItem extends BetaExamQuestion, BetaExamGiven {
    explanation: string;
    legal_basis: string;
}

export interface BetaExamResult {
    correct: number;
    answered: number;
    total: number;
    xp_gained: number;
    energy_gained: number;
    best_correct: number;
    discipline_level_up: DisciplineLevelUp | null;
    user: { xp: number; energy: EnergyState };
    review: BetaExamReviewItem[];
}

export interface BetaExamAnswerResponse {
    is_correct: boolean;
    correct_answer: string;
    // XP e energia somados na prova até aqui (só são creditados na entrega)
    xp: number;
    energy: number;
    finished: boolean;
    result: BetaExamResult | null;
}

/** Energia do mapa novo (EnergyService::payload no servidor). */
export interface EnergyState {
    value: number;
    max: number;
    // Hora do próximo +1 (nula com a barra cheia ou infinita)
    next_at: string | null;
    infinite: boolean;
}

/** Combo: a sequência de acertos fechou um múltiplo e devolveu energia. */
export interface EnergyCombo {
    streak: number;
    gained: number;
}

/** Resposta de cada bloco verificado na fase (e de cada questão nova do desafio). */
export interface EnergySpend {
    energy: EnergyState;
    combo: EnergyCombo | null;
    out_of_energy: boolean;
}

export interface BetaFinishResponse {
    xp_gained: number;
    discipline_level_up: DisciplineLevelUp | null;
    user: PlayUserData & { energy: EnergyState };
}
