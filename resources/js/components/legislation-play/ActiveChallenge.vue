<script setup lang="ts">
import type { ActiveSegmentData, SegmentAnswer } from '@/types/legislation-play';
import { ref, computed } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import GameButton from '@/components/ui/GameButton.vue';

const props = defineProps<{
    segment: ActiveSegmentData;
    controlsTarget?: string;
}>();

const emit = defineEmits<{
    submit: [data: {
        segment_uuid: string;
        answers: SegmentAnswer[];
        correct_count: number;
        total_count: number;
    }];
}>();

interface DisplayToken {
    type: 'text' | 'space' | 'lacuna' | 'marker';
    text: string;
    wordIndex: number;
    gapOrder?: number;
    correctWord?: string;
}

// Estado das seleções do jogador: gap_order -> palavra selecionada
const userSelections = ref<Record<number, string>>({});
const isVerified = ref(false);
const verificationResults = ref<SegmentAnswer[]>([]);

const displayTokens = computed((): DisplayToken[] => {
    const parts = props.segment.original_text.split(/(\s+)/);
    const lacunaMap = new Map(props.segment.lacunas.map((l) => [l.word_position, l]));
    let wordIndex = 0;

    // Count words in the structural marker to skip them (they're displayed separately)
    let markerWordCount = 0;
    if (props.segment.structural_marker) {
        markerWordCount = props.segment.structural_marker.split(/\s+/).filter((w: string) => w.length > 0).length;
    }

    return parts.map((part) => {
        if (/^\s+$/.test(part)) {
            return { type: 'space' as const, text: part, wordIndex: -1 };
        }
        const idx = wordIndex;
        wordIndex++;

        // Skip tokens that are part of the structural marker (already shown in bold)
        if (idx < markerWordCount) {
            return { type: 'marker' as const, text: '', wordIndex: idx };
        }
        // Skip dash/separator right after the marker (e.g. "–" in "V – igualdade")
        if (idx === markerWordCount && /^[-–—]$/.test(part)) {
            return { type: 'marker' as const, text: '', wordIndex: idx };
        }

        const lacuna = lacunaMap.get(idx);
        if (lacuna) {
            return {
                type: 'lacuna' as const,
                text: part,
                wordIndex: idx,
                gapOrder: lacuna.gap_order,
                correctWord: lacuna.word,
            };
        }
        return { type: 'text' as const, text: part, wordIndex: idx };
    });
});

// Palavras disponíveis (não selecionadas)
const availableOptions = computed(() => {
    // Conta quantas vezes cada palavra foi selecionada
    const selectedCounts: Record<string, number> = {};
    for (const word of Object.values(userSelections.value)) {
        selectedCounts[word] = (selectedCounts[word] || 0) + 1;
    }
    // Conta quantas vezes cada opção aparece no total
    const totalCounts: Record<string, number> = {};
    for (const word of props.segment.options) {
        totalCounts[word] = (totalCounts[word] || 0) + 1;
    }
    // Remove as selecionadas
    const result: string[] = [];
    const usedCounts: Record<string, number> = {};
    for (const word of props.segment.options) {
        usedCounts[word] = (usedCounts[word] || 0) + 1;
        const used = selectedCounts[word] || 0;
        if (usedCounts[word] > used) {
            result.push(word);
        }
    }
    return result;
});

const hasLacunas = computed(() => props.segment.lacunas.length > 0);

const allFilled = computed(() => {
    return props.segment.lacunas.every((l) => userSelections.value[l.gap_order]);
});

// Próxima lacuna vazia
function nextEmptyGapOrder(): number | null {
    for (const lacuna of props.segment.lacunas) {
        if (!userSelections.value[lacuna.gap_order]) {
            return lacuna.gap_order;
        }
    }
    return null;
}

function selectWord(word: string) {
    if (isVerified.value) return;
    const gapOrder = nextEmptyGapOrder();
    if (gapOrder === null) return;
    userSelections.value[gapOrder] = word;
}

function unselectGap(gapOrder: number) {
    if (isVerified.value) return;
    delete userSelections.value[gapOrder];
}

function clearAll() {
    if (isVerified.value) return;
    userSelections.value = {};
}

function verify() {
    if (!allFilled.value || isVerified.value) return;

    const results: SegmentAnswer[] = props.segment.lacunas.map((lacuna) => ({
        gap_order: lacuna.gap_order,
        word_position: lacuna.word_position,
        correct_word: lacuna.word,
        user_word: userSelections.value[lacuna.gap_order] || '',
        is_correct: userSelections.value[lacuna.gap_order] === lacuna.word,
    }));

    verificationResults.value = results;
    isVerified.value = true;

    const correctCount = results.filter((r) => r.is_correct).length;

    emit('submit', {
        segment_uuid: props.segment.uuid,
        answers: results,
        correct_count: correctCount,
        total_count: results.length,
    });
}

function getVerificationResult(gapOrder: number): SegmentAnswer | undefined {
    return verificationResults.value.find((r) => r.gap_order === gapOrder);
}

// Reset quando o segmento muda
function reset() {
    userSelections.value = {};
    isVerified.value = false;
    verificationResults.value = [];
}

defineExpose({ reset });
</script>

<template>
    <div class="relative py-6">
        <!-- Texto com lacunas (fica dentro do scroll) -->
        <div class="font-body text-xl md:text-2xl leading-relaxed text-gray-900 dark:text-white font-medium whitespace-pre-line">
            <p>
                <span
                    v-if="segment.structural_marker"
                    class="font-bold text-black dark:text-white"
                >{{ segment.structural_marker }} </span><template
                    v-for="(token, ti) in displayTokens"
                    :key="ti"
                ><template v-if="token.type === 'marker'" /><span v-else-if="token.type === 'space'">{{ token.text }}</span><template
                        v-else-if="token.type === 'lacuna'"
                    ><!-- Antes de verificar -->
                        <span
                            v-if="!isVerified && !userSelections[token.gapOrder!]"
                            class="lacuna-empty inline-block font-semibold mx-0.5 select-none px-1 py-0.5 rounded-md"
                        >(...)</span>
                        <span
                            v-else-if="!isVerified && userSelections[token.gapOrder!]"
                            class="lacuna-filled inline-flex items-center font-bold mx-0.5 cursor-pointer px-1.5 py-0.5 rounded transition-all"
                            @click="unselectGap(token.gapOrder!)"
                        >{{ userSelections[token.gapOrder!] }}<span class="lacuna-remove-indicator ml-1 text-sm font-bold" style="vertical-align: -1px">×</span></span>
                        <!-- Após verificar -->
                        <span
                            v-else-if="isVerified && getVerificationResult(token.gapOrder!)?.is_correct"
                            class="bg-green-100/60 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full px-2.5 py-0.5 font-bold mx-0.5"
                        >{{ token.correctWord }}</span>
                        <span
                            v-else-if="isVerified"
                            class="bg-red-100/60 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-full px-2.5 py-0.5 font-bold mx-0.5"
                            :title="`Correto: ${token.correctWord}`"
                        >{{ getVerificationResult(token.gapOrder!)?.user_word }}</span>
                    </template><span v-else>{{ token.text }}</span></template>
            </p>
        </div>
    </div>

    <!-- Controles teleportados para fora do scroll -->
    <Teleport defer :to="controlsTarget" v-if="controlsTarget">
        <!-- Opções e ações -->
        <div v-if="!isVerified">
            <!-- Botões de palavras -->
            <div v-if="hasLacunas" class="flex flex-wrap gap-3 pt-4 mb-6">
                <GameButton
                    v-for="(word, wi) in availableOptions"
                    :key="wi"
                    variant="white"
                    size="sm"
                    @click="selectWord(word)"
                >
                    {{ word }}
                </GameButton>
            </div>

            <!-- Ações -->
            <div class="flex justify-between pt-4 border-t">
                <GameButton
                    v-if="hasLacunas"
                    variant="white"
                    size="sm"
                    class="flex items-center gap-2"
                    @click="clearAll"
                >
                    <RefreshCw class="h-4 w-4" />
                    Limpar
                </GameButton>
                <div v-else />

                <GameButton
                    :disabled="!allFilled"
                    variant="purple"
                    size="sm"
                    @click="verify"
                >
                    Verificar
                </GameButton>
            </div>
        </div>

        <!-- Resultado após verificação -->
        <div v-else class="text-center py-2">
            <div v-if="segment.total_gaps > 0" class="flex items-center justify-center gap-3">
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold"
                    :class="verificationResults.filter(r => r.is_correct).length >= Math.ceil(segment.total_gaps * 0.7)
                        ? 'bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400'
                        : 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400'"
                >
                    {{ verificationResults.filter(r => r.is_correct).length >= Math.ceil(segment.total_gaps * 0.7) ? '✓' : '✗' }}
                </div>
                <div class="text-base font-semibold text-gray-700 dark:text-gray-300">
                    {{ verificationResults.filter(r => r.is_correct).length }} de {{ segment.total_gaps }} corretas
                    <span class="text-sm text-gray-400">
                        ({{ Math.round((verificationResults.filter(r => r.is_correct).length / segment.total_gaps) * 100) }}%)
                    </span>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Fallback inline (sem teleport target) -->
    <template v-if="!controlsTarget">
        <div v-if="!isVerified" class="px-3">
            <div v-if="hasLacunas" class="flex flex-wrap gap-3 pt-4 mb-6">
                <GameButton
                    v-for="(word, wi) in availableOptions"
                    :key="wi"
                    variant="white"
                    size="sm"
                    @click="selectWord(word)"
                >
                    {{ word }}
                </GameButton>
            </div>
            <div class="flex justify-between pt-4 border-t">
                <GameButton v-if="hasLacunas" variant="white" size="sm" class="flex items-center gap-2" @click="clearAll">
                    <RefreshCw class="h-4 w-4" /> Limpar
                </GameButton>
                <div v-else />
                <GameButton :disabled="!allFilled" variant="purple" size="sm" @click="verify">Verificar</GameButton>
            </div>
        </div>
    </template>
</template>

<style>
.lacuna-empty {
    background: #fffbcd;
    color: #8e7e26;
}

:global(.dark) .lacuna-empty {
    background: #242424;
    color: #fffbcd;
}

.lacuna-filled {
    background-color: rgb(240, 249, 255);
    border-bottom: 2px solid rgb(56, 189, 248);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    color: rgb(30, 41, 59);
    line-height: 1.2;
    vertical-align: baseline;
    font-size: inherit;
}

:global(.dark) .lacuna-filled {
    background-color: rgb(30, 59, 138);
    border-bottom-color: rgb(5, 36, 121);
    color: rgb(226, 232, 240);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.lacuna-remove-indicator {
    color: rgb(148, 163, 184);
}

:global(.dark) .lacuna-remove-indicator {
    color: rgba(239, 239, 239, 0.7);
}

.lacuna-filled:hover .lacuna-remove-indicator {
    color: rgb(239, 68, 68);
}

:global(.dark) .lacuna-filled:hover .lacuna-remove-indicator {
    color: rgb(252, 165, 165);
}
</style>
