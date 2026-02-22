<script setup lang="ts">
import type { LegislationSegment } from '@/types/legislation';
import BlockCard from './BlockCard.vue';
import { Search, ChevronUp, ChevronDown } from 'lucide-vue-next';
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps<{
    rawText: string;
    segments: LegislationSegment[];
    activeSegmentUuid: string | null;
}>();

const emit = defineEmits<{
    selectSegment: [uuid: string];
    removeBlock: [uuid: string];
    toggleLacuna: [segmentUuid: string, word: string, position: number];
    removeLacuna: [lacunaUuid: string];
}>();

const searchQuery = ref('');
const scrollContainer = ref<HTMLElement | null>(null);
const currentMatchIndex = ref(0);

interface InteractiveToken {
    type: 'text' | 'block';
    text: string;
    charStart: number;
    charEnd: number;
    segment?: LegislationSegment;
}

interface TextPart {
    text: string;
    highlight: boolean;
}

/**
 * Tokeniza o raw_text intercalando texto livre e blocos existentes.
 */
const tokens = computed((): InteractiveToken[] => {
    const text = props.rawText;
    if (!text) return [];

    const ranges = props.segments
        .filter(s => s.char_start != null && s.char_end != null)
        .map(s => ({
            start: s.char_start,
            end: s.char_end,
            segment: s,
        }))
        .sort((a, b) => a.start - b.start);

    const result: InteractiveToken[] = [];
    let cursor = 0;

    for (const range of ranges) {
        if (cursor < range.start) {
            result.push({
                type: 'text',
                text: text.slice(cursor, range.start),
                charStart: cursor,
                charEnd: range.start,
            });
        }

        result.push({
            type: 'block',
            text: text.slice(range.start, range.end),
            charStart: range.start,
            charEnd: range.end,
            segment: range.segment,
        });

        cursor = range.end;
    }

    if (cursor < text.length) {
        result.push({
            type: 'text',
            text: text.slice(cursor, text.length),
            charStart: cursor,
            charEnd: text.length,
        });
    }

    return result;
});

const domMatchCount = ref(0);

function splitBySearch(text: string): TextPart[] {
    const query = searchQuery.value;
    if (!query || query.length < 2) return [{ text, highlight: false }];

    const lowerQuery = query.toLowerCase();
    const lowerText = text.toLowerCase();
    const parts: TextPart[] = [];
    let lastIndex = 0;

    let pos = lowerText.indexOf(lowerQuery);
    while (pos !== -1) {
        if (pos > lastIndex) {
            parts.push({ text: text.slice(lastIndex, pos), highlight: false });
        }
        parts.push({ text: text.slice(pos, pos + query.length), highlight: true });
        lastIndex = pos + query.length;
        pos = lowerText.indexOf(lowerQuery, lastIndex);
    }

    if (lastIndex < text.length) {
        parts.push({ text: text.slice(lastIndex), highlight: false });
    }

    return parts.length > 0 ? parts : [{ text, highlight: false }];
}

function scrollToCurrentMatch() {
    nextTick(() => {
        if (!scrollContainer.value) return;
        const all = scrollContainer.value.querySelectorAll('.search-highlight');
        domMatchCount.value = all.length;
        all.forEach(el => el.classList.remove('search-highlight-active'));
        if (all.length === 0) return;
        const idx = Math.min(currentMatchIndex.value, all.length - 1);
        currentMatchIndex.value = idx;
        all[idx].classList.add('search-highlight-active');
        all[idx].scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function goToPrevMatch() {
    if (domMatchCount.value <= 1) return;
    currentMatchIndex.value = (currentMatchIndex.value - 1 + domMatchCount.value) % domMatchCount.value;
    scrollToCurrentMatch();
}

function goToNextMatch() {
    if (domMatchCount.value <= 1) return;
    currentMatchIndex.value = (currentMatchIndex.value + 1) % domMatchCount.value;
    scrollToCurrentMatch();
}

watch(searchQuery, () => {
    currentMatchIndex.value = 0;
    scrollToCurrentMatch();
});
</script>

<template>
    <div class="flex-1 flex flex-col overflow-hidden">
        <div class="py-3 px-6 border-b border-gray-100 dark:border-gray-800 space-y-2">
            <h2 class="text-[11px] font-bold uppercase text-gray-900 dark:text-gray-100 tracking-widest">
                Editor de Blocos
            </h2>
            <div class="flex items-center gap-2">
                <div class="relative flex-1 max-w-xs">
                    <Search class="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar nos blocos..."
                        class="w-full pl-7 pr-2 py-1.5 text-xs border border-gray-200 dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
                    />
                </div>
                <template v-if="searchQuery.length >= 2">
                    <span class="text-[10px] text-gray-400 whitespace-nowrap">
                        {{ domMatchCount > 0 ? `${currentMatchIndex + 1}/${domMatchCount}` : '0' }}
                    </span>
                    <div v-if="domMatchCount > 1" class="flex items-center">
                        <button @click="goToPrevMatch" class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Anterior">
                            <ChevronUp class="w-3.5 h-3.5" />
                        </button>
                        <button @click="goToNextMatch" class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Próximo">
                            <ChevronDown class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <div ref="scrollContainer" class="flex-1 overflow-y-auto p-6">
            <div class="max-w-3xl mx-auto">
                <div class="text-[13px] leading-relaxed">
                    <template v-for="(token, ti) in tokens" :key="ti">
                        <!-- Texto sem bloco -->
                        <span
                            v-if="token.type === 'text'"
                            class="whitespace-pre-wrap text-gray-400 dark:text-gray-500"
                        ><template v-for="(part, pi) in splitBySearch(token.text)" :key="pi"><mark v-if="part.highlight" class="search-highlight bg-yellow-300 dark:bg-yellow-700 text-inherit rounded-sm">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template></span>

                        <!-- Bloco existente: renderiza BlockCard inline -->
                        <div v-else>
                            <BlockCard
                                :id="`segment-${token.segment!.uuid}`"
                                :segment="token.segment!"
                                :is-active="activeSegmentUuid === token.segment!.uuid"
                                :search-query="searchQuery"
                                @remove-block="emit('removeBlock', token.segment!.uuid)"
                                @toggle-lacuna="(word: string, pos: number) => emit('toggleLacuna', token.segment!.uuid, word, pos)"
                                @remove-lacuna="(uuid: string) => emit('removeLacuna', uuid)"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.search-highlight-active) {
    background-color: rgb(251 146 60 / 0.7) !important;
    outline: 2px solid rgb(251 146 60);
    outline-offset: -1px;
    border-radius: 2px;
}

:deep(.dark .search-highlight-active) {
    background-color: rgb(194 65 12 / 0.7) !important;
    outline-color: rgb(194 65 12);
}
</style>
