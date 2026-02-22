<script setup lang="ts">
import type { LegislationSegment } from '@/types/legislation';
import LacunaTagBar from './LacunaTagBar.vue';
import { Badge } from '@/components/ui/badge';
import { X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    segment: LegislationSegment;
    isActive?: boolean;
    searchQuery?: string;
}>();

const emit = defineEmits<{
    removeBlock: [];
    toggleLacuna: [word: string, position: number];
    removeLacuna: [uuid: string];
}>();

/**
 * Tokeniza o texto em palavras, preservando espaços e posições de caracteres.
 */
const tokens = computed(() => {
    const parts = props.segment.original_text.split(/(\s+)/);
    let wordIndex = 0;
    let charPos = 0;
    return parts.map((part) => {
        const start = charPos;
        charPos += part.length;
        if (/^\s+$/.test(part)) {
            return { text: part, isSpace: true, wordIndex: -1, charStart: start, charEnd: charPos };
        }
        const idx = wordIndex;
        wordIndex++;
        return { text: part, isSpace: false, wordIndex: idx, charStart: start, charEnd: charPos };
    });
});

const lacunaPositions = computed(() => {
    const positions = new Set<number>();
    for (const lac of props.segment.lacunas) {
        if (lac.is_correct) {
            positions.add(lac.word_position);
        }
    }
    return positions;
});

const correctLacunas = computed(() =>
    props.segment.lacunas
        .filter((l) => l.is_correct)
        .sort((a, b) => (a.gap_order ?? 0) - (b.gap_order ?? 0)),
);

const distractors = computed(() =>
    props.segment.lacunas.filter((l) => !l.is_correct),
);

function onWordClick(word: string, position: number) {
    emit('toggleLacuna', word, position);
}

/**
 * Encontra todas as ocorrências da query como substring no original_text.
 */
const searchMatchRanges = computed(() => {
    if (!props.searchQuery || props.searchQuery.length < 2) return [];
    const query = props.searchQuery.toLowerCase();
    const text = props.segment.original_text.toLowerCase();
    const ranges: Array<{ start: number; end: number }> = [];
    let pos = text.indexOf(query);
    while (pos !== -1) {
        ranges.push({ start: pos, end: pos + query.length });
        pos = text.indexOf(query, pos + query.length);
    }
    return ranges;
});

function isSearchMatch(charStart: number, charEnd: number): boolean {
    return searchMatchRanges.value.some(
        (range) => charStart < range.end && charEnd > range.start,
    );
}
</script>

<template>
    <div
        class="rounded-lg border p-4 transition-colors bg-white dark:bg-gray-800"
        :class="isActive
            ? 'border-blue-400 dark:border-blue-500 ring-1 ring-blue-200 dark:ring-blue-800'
            : 'border-gray-300 dark:border-gray-600'"
    >
        <div class="flex items-start justify-between gap-2 mb-2">
            <div class="flex items-center gap-2">
                <Badge variant="outline" class="text-[10px]">{{ segment.segment_type }}</Badge>
                <span v-if="segment.structural_marker" class="text-[11px] text-gray-400 font-mono">
                    {{ segment.structural_marker }}
                </span>
                <span v-if="segment.article_reference" class="text-[10px] text-gray-400">
                    Art. {{ segment.article_reference }}
                </span>
            </div>
            <button
                @click="emit('removeBlock')"
                class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                title="Remover bloco"
            >
                <X class="w-4 h-4" />
            </button>
        </div>

        <div class="text-sm leading-relaxed whitespace-pre-line">
            <template v-for="(token, ti) in tokens" :key="ti">
                <span v-if="token.isSpace">{{ token.text }}</span>
                <span
                    v-else
                    @click="onWordClick(token.text, token.wordIndex)"
                    class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-sm px-0.5 transition-colors"
                    :class="{
                        'border-b-2 border-dashed border-blue-400 font-medium text-blue-700 dark:text-blue-300': lacunaPositions.has(token.wordIndex),
                        'search-highlight bg-yellow-300/60 dark:bg-yellow-700/60': isSearchMatch(token.charStart, token.charEnd),
                    }"
                >{{ token.text }}</span>
            </template>
        </div>

        <LacunaTagBar
            :lacunas="[...correctLacunas, ...distractors]"
            @remove-lacuna="(uuid) => emit('removeLacuna', uuid)"
        />
    </div>
</template>
