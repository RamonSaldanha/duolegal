// Material de estudo (resumo + questões) gravado por agentes de IA.

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
export interface BonusQuestion {
    position: number;
    board: StudyBoard;
    type: StudyQuestionType;
    statement: string;
    options: Record<string, string> | null;
}

export interface BonusAnswerResponse {
    is_correct: boolean;
    correct_answer: string;
    explanation: string;
    legal_basis: string;
}

export interface BonusFinishResponse {
    correct_count: number;
    total: number;
    xp_gained: number;
    first_completion: boolean;
}
