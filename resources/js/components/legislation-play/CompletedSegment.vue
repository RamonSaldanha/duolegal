<script setup lang="ts">
import type { CompletedSegmentData, SegmentAnswer } from '@/types/legislation-play';
import { computed } from 'vue';

const props = defineProps<{
    segment: CompletedSegmentData;
}>();

interface DisplayToken {
    text: string;
    type: 'text' | 'space' | 'answer' | 'marker';
    wordIndex: number;
    answer?: SegmentAnswer;
}

const tokens = computed((): DisplayToken[] => {
    const parts = props.segment.original_text.split(/(\s+)/);
    const answerMap = new Map<number, SegmentAnswer>();

    if (props.segment.answers) {
        for (const ans of props.segment.answers) {
            answerMap.set(ans.word_position, ans);
        }
    }

    // Count words in structural marker to skip (shown separately)
    let markerWordCount = 0;
    if (props.segment.structural_marker) {
        markerWordCount = props.segment.structural_marker.split(/\s+/).filter((w: string) => w.length > 0).length;
    }

    let wordIndex = 0;
    return parts.map((part) => {
        if (/^\s+$/.test(part)) {
            return { text: part, type: 'space' as const, wordIndex: -1 };
        }
        const idx = wordIndex;
        wordIndex++;

        // Skip tokens that belong to the structural marker
        if (idx < markerWordCount) {
            return { text: '', type: 'marker' as const, wordIndex: idx };
        }
        // Skip separator dash after marker
        if (idx === markerWordCount && /^[-–—]$/.test(part)) {
            return { text: '', type: 'marker' as const, wordIndex: idx };
        }

        const answer = answerMap.get(idx);
        if (answer) {
            return { text: part, type: 'answer' as const, wordIndex: idx, answer };
        }
        return { text: part, type: 'text' as const, wordIndex: idx };
    });
});
</script>

<template>
    <div class="select-none opacity-60 hover:opacity-100 transition-opacity duration-300">
        <p class="text-lg font-body leading-relaxed text-gray-400 dark:text-gray-600 whitespace-pre-line">
            <span
                v-if="segment.structural_marker"
                class="font-bold text-gray-500 dark:text-gray-500"
            >{{ segment.structural_marker }} </span><template
                v-for="(token, ti) in tokens"
                :key="ti"
            ><template v-if="token.type === 'marker'" /><span v-else-if="token.type === 'space'">{{ token.text }}</span><span
                    v-else-if="token.answer?.is_correct"
                    class="bg-green-100/60 dark:bg-green-900/30 text-green-700/70 dark:text-green-300/70 rounded-full px-2.5 py-0.5 font-bold mx-0.5"
                >{{ token.text }}</span><span
                    v-else-if="token.answer && !token.answer.is_correct"
                    class="mx-0.5 inline"
                ><span class="bg-red-100/50 dark:bg-red-900/20 text-red-400/70 dark:text-red-400/50 rounded-full px-2 py-0.5 line-through decoration-2 text-base">{{ token.answer.user_word }}</span> <span class="bg-green-100/60 dark:bg-green-900/30 text-green-700/70 dark:text-green-300/70 rounded-full px-2.5 py-0.5 font-bold">{{ token.answer.correct_word }}</span></span><span v-else>{{ token.text }}</span></template>
        </p>
    </div>
</template>
