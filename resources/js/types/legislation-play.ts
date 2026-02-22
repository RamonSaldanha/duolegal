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

export interface SubmitAnswerResponse {
    success: boolean;
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
