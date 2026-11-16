// Material de estudo (resumo + questões) gravado por agentes de IA.

import type { DisciplineLevelUp } from '@/types/legislation-play';

export type StudyBoard = 'cebraspe' | 'fgv' | 'fcc' | 'vunesp';
export type StudyQuestionType = 'true_false' | 'multiple_choice';
export type StudyStatus = 'draft' | 'published' | 'skipped';

export const STUDY_BOARDS: { value: StudyBoard; label: string }[] = [
    { value: 'cebraspe', label: 'Cebraspe' },
    { value: 'fgv', label: 'FGV' },
    { value: 'fcc', label: 'FCC' },
    { value: 'vunesp', label: 'Vunesp' },
];

export const STUDY_OPTION_KEYS = ['A', 'B', 'C', 'D', 'E'] as const;

export const boardLabel = (board: string) => STUDY_BOARDS.find((item) => item.value === board)?.label ?? board;

/** Questão completa, com gabarito: só no admin. */
export interface StudyQuestionForm {
    board: StudyBoard;
    type: StudyQuestionType;
    statement: string;
    options: Record<string, string> | null;
    correct_answer: string;
    explanation: string;
    legal_basis: string;
}

export interface StudyMaterialRow {
    uuid: string;
    title: string | null;
    status: StudyStatus;
    is_stale: boolean;
    skip_reason: string | null;
    first_label: string | null;
    last_label: string | null;
    range_blocks: number | null;
    covered_blocks: number;
    questions_count: number;
    square: number | null;
    updated_at: string | null;
}

/** Questão como o jogador recebe: sem gabarito, comentário nem fundamento. */
export interface CheckpointQuestion {
    position: number;
    board: StudyBoard;
    type: StudyQuestionType;
    statement: string;
    options: Record<string, string> | null;
}

/** O que o servidor devolve depois de gravar um passo (resumo lido ou resposta). */
export interface CheckpointStepResponse {
    /** Este passo concluiu o material no quadrado */
    material_completed: boolean;
    xp_gained: number;
    discipline_level_up: DisciplineLevelUp | null;
    checkpoint: { completed: number; total: number };
    user: { xp: number };
}

export interface CheckpointAnswerResponse extends CheckpointStepResponse {
    is_correct: boolean;
    correct_answer: string;
    explanation: string;
    legal_basis: string;
}

/** Material como o desafio do quadrado recebe: resumo já em HTML seguro e questões sem gabarito. */
export interface CheckpointMaterial {
    uuid: string;
    title: string;
    legislation_title: string;
    summary_html: string;
    questions: CheckpointQuestion[];
    done: boolean;
    /** Progresso guardado de um material ainda não concluído: resumo lido e questões respondidas */
    summary_read: boolean;
    answered: number[];
}
