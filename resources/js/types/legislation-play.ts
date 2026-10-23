export interface LegislationPlayItem {
    uuid: string;
    title: string;
    total_blocks: number;
    completed_blocks: number;
    percentage: number;
    source_url: string | null;
}

export interface SegmentAnswer {
    gap_order: number;
    word_position: number;
    correct_word: string;
    user_word: string;
    is_correct: boolean;
}

export interface CompletedSegmentData {
    uuid: string;
    original_text: string;
    position: number;
    segment_type: string;
    article_reference: string | null;
    structural_marker: string | null;
    answers: SegmentAnswer[] | null;
    is_completed: boolean;
    best_score: number;
}

export interface LacunaData {
    gap_order: number;
    word: string;
    word_position: number;
}

export interface ActiveSegmentData {
    uuid: string;
    original_text: string;
    position: number;
    segment_type: string;
    article_reference: string | null;
    structural_marker: string | null;
    lacunas: LacunaData[];
    options: string[];
    total_gaps: number;
}

export interface PlayProgress {
    current: number;
    total: number;
    percentage: number;
}

export interface PlayUserData {
    lives: number;
    has_infinite_lives: boolean;
    xp: number;
}

export interface DisciplineLevelUp {
    discipline_id: number;
    discipline_name: string;
    icon: string;
    color: string;
    old_level: number;
    new_level: number;
}

/**
 * Acertos necessários para passar num bloco (espelha UserSegmentProgress::requiredCorrect):
 * pode errar no máximo 1 lacuna (1 lacuna exige o acerto), nunca acima dos 70% da regra antiga.
 */
export function requiredCorrect(totalGaps: number): number {
    if (totalGaps <= 0) return 0;
    if (totalGaps === 1) return 1;
    return Math.min(totalGaps - 1, Math.ceil(totalGaps * 0.7));
}

export interface SubmitAnswerResponse {
    success: boolean;
    passed?: boolean;
    progress: {
        is_completed: boolean;
        best_score: number;
        attempts: number;
        percentage: number;
    };
    answers: SegmentAnswer[];
    next_segment: ActiveSegmentData | null;
    user: PlayUserData;
    xp_gained: number;
    discipline_level_up: DisciplineLevelUp | null;
    lost_life: boolean;
    should_redirect: boolean;
    redirect_url: string | null;
}

// === Beta Map Types ===

export interface BetaPhaseProgress {
    completed: number;
    total: number;
    percentage: number;
    block_status: Array<'correct' | 'incorrect' | 'pending'>;
}

export interface BetaPhase {
    id: number;
    legislation_uuid: string;
    legislation_title: string;
    block_count: number;
    is_complete: boolean;
    is_current: boolean;
    is_blocked: boolean;
    show_legislation_header: boolean;
    progress: BetaPhaseProgress;
}

export interface BetaMapLegislation {
    uuid: string;
    title: string;
    total_blocks: number;
}

export interface LoadMorePhasesResponse {
    phases: BetaPhase[];
    hasMore: boolean;
}
