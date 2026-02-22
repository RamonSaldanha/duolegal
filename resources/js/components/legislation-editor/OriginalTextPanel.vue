<script setup lang="ts">
import type { LegislationSegment } from '@/types/legislation';
import { Search, ChevronUp, ChevronDown } from 'lucide-vue-next';
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps<{
    rawText: string;
    segments: LegislationSegment[];
}>();

const searchQuery = ref('');
const scrollContainer = ref<HTMLElement | null>(null);
const currentMatchIndex = ref(0);

interface TextToken {
    text: string;
    isBlock: boolean;
}

interface TextPart {
    text: string;
    highlight: boolean;
}

/**
 * Tokeniza o raw_text em fragmentos, marcando quais pertencem a blocos existentes.
 */
const tokens = computed((): TextToken[] => {
    const text = props.rawText;
    if (!text) return [];

    const ranges = props.segments
        .filter(s => s.char_start != null && s.char_end != null)
        .map(s => ({ start: s.char_start, end: s.char_end }))
        .sort((a, b) => a.start - b.start);

    const result: TextToken[] = [];
    let cursor = 0;

    for (const range of ranges) {
        if (cursor < range.start) {
            result.push({ text: text.slice(cursor, range.start), isBlock: false });
        }
        result.push({ text: text.slice(range.start, range.end), isBlock: true });
        cursor = range.end;
    }

    if (cursor < text.length) {
        result.push({ text: text.slice(cursor, text.length), isBlock: false });
    }

    return result;
});

const matchCount = computed(() => {
    if (!searchQuery.value || searchQuery.value.length < 2) return 0;
    const query = searchQuery.value.toLowerCase();
    const text = props.rawText.toLowerCase();
    let count = 0;
    let pos = 0;
    while ((pos = text.indexOf(query, pos)) !== -1) {
        count++;
        pos += query.length;
    }
    return count;
});

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
        all.forEach(el => el.classList.remove('search-highlight-active'));
        if (all.length === 0) return;
        const idx = Math.min(currentMatchIndex.value, all.length - 1);
        all[idx].classList.add('search-highlight-active');
        all[idx].scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function goToPrevMatch() {
    if (matchCount.value === 0) return;
    currentMatchIndex.value = (currentMatchIndex.value - 1 + matchCount.value) % matchCount.value;
    scrollToCurrentMatch();
}

function goToNextMatch() {
    if (matchCount.value === 0) return;
    currentMatchIndex.value = (currentMatchIndex.value + 1) % matchCount.value;
    scrollToCurrentMatch();
}

watch(searchQuery, () => {
    currentMatchIndex.value = 0;
    scrollToCurrentMatch();
});
</script>

<template>
    <aside class="w-[480px] bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col hidden lg:flex">
        <div class="py-3 px-5 border-b border-gray-100 dark:border-gray-800 space-y-2">
            <h2 class="text-[11px] font-bold uppercase text-gray-900 dark:text-gray-100 tracking-widest">
                Texto Original
            </h2>
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar no texto..."
                        class="w-full pl-7 pr-2 py-1.5 text-xs border border-gray-200 dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
                    />
                </div>
                <template v-if="searchQuery.length >= 2">
                    <span class="text-[10px] text-gray-400 whitespace-nowrap">
                        {{ matchCount > 0 ? `${currentMatchIndex + 1}/${matchCount}` : '0' }}
                    </span>
                    <div v-if="matchCount > 1" class="flex items-center">
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
        <div ref="scrollContainer" class="flex-1 overflow-y-auto p-5 text-[13px] leading-relaxed select-text">
            <template v-for="(token, ti) in tokens" :key="ti">
                <span
                    v-if="token.isBlock"
                    class="whitespace-pre-wrap border-l-2 border-emerald-500 bg-emerald-50/50 dark:bg-emerald-900/20 px-1 -ml-1 text-gray-800 dark:text-gray-200"
                ><template v-for="(part, pi) in splitBySearch(token.text)" :key="pi"><mark v-if="part.highlight" class="search-highlight bg-yellow-300 dark:bg-yellow-700 text-inherit rounded-sm">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template></span>
                <span
                    v-else
                    class="whitespace-pre-wrap text-gray-700 dark:text-gray-300"
                ><template v-for="(part, pi) in splitBySearch(token.text)" :key="pi"><mark v-if="part.highlight" class="search-highlight bg-yellow-300 dark:bg-yellow-700 text-inherit rounded-sm">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template></span>
            </template>
        </div>
    </aside>
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
