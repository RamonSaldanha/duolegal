export interface SegmentLacuna {
    uuid: string;
    word: string;
    word_position: number;
    is_correct: boolean;
    gap_order: number | null;
}

export interface LegislationSegment {
    uuid: string;
    original_text: string;
    char_start: number;
    char_end: number;
    segment_type: string;
    article_reference: string | null;
    structural_marker: string | null;
    position: number;
    is_block: boolean;
    lacunas: SegmentLacuna[];
    /** Alunos que já concluíram o bloco (remover o bloco apaga esse progresso). */
    completed_count?: number;
}

/** A partir de quantas palavras um bloco é destacado como grande no editor. */
export const BIG_BLOCK_WORDS = 100;

export function wordCount(text: string): number {
    return text.split(/\s+/).filter((w) => w.length > 0).length;
}

export interface Legislation {
    uuid: string;
    title: string;
    source_url: string | null;
    raw_text: string;
    status: string;
    legal_reference: string | null;
}

export interface LegislationListItem {
    uuid: string;
    title: string;
    status: string;
    source_url: string | null;
    legal_reference: string | null;
    segments_count: number;
    blocks_count: number;
    created_at: string;
}
