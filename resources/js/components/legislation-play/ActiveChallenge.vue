<script setup lang="ts">
import type { ActiveSegmentData, SegmentAnswer } from '@/types/legislation-play';
import { requiredCorrect } from '@/types/legislation-play';
import { ref, computed, nextTick } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import GameButton from '@/components/ui/GameButton.vue';

const props = defineProps<{
    segment: ActiveSegmentData;
    controlsTarget?: string;
    /** Esconde o ✓/✗ do resultado (a tela que usa já mostra o acerto ou o erro). */
    hideResultIcon?: boolean;
    // A tela mostra o resultado por conta própria (barra de resultado)
    hideResult?: boolean;
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
// De qual botão do banco veio cada palavra: gap_order -> índice em segment.options
const selectedOption = ref<Record<number, number>>({});
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

// O banco não se reorganiza: a palavra usada deixa o lugar dela vazio, e volta para lá
const usedOptions = computed(() => new Set(Object.values(selectedOption.value)));

// Enquanto a palavra voa entre o banco e a lacuna, o destino fica escondido
// ('gap-<gap_order>' ou 'option-<índice>')
const inFlight = ref<Record<string, boolean>>({});
const textRef = ref<HTMLElement | null>(null);
const optionSlots = new Map<number, HTMLElement>();

function setOptionSlot(index: number, element: unknown) {
    if (element instanceof HTMLElement) optionSlots.set(index, element);
    else optionSlots.delete(index);
}

const isOptionEmpty = (index: number) => usedOptions.value.has(index) || !!inFlight.value[`option-${index}`];

const FLIGHT_MS = 220;
const center = (rect: DOMRect) => ({ x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 });

/**
 * Leva uma cópia do botão da palavra de um ponto a outro da tela (do banco até a lacuna, ou
 * de volta) e só então mostra o destino. Quem pediu menos movimento não vê a animação.
 */
function fly(optionIndex: number, from: DOMRect, to: DOMRect, destination: string) {
    const button = optionSlots.get(optionIndex)?.firstElementChild;
    if (!(button instanceof HTMLElement) || !button.animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // O lugar do botão no banco não muda, então a cópia nasce ali e é deslocada
    const home = button.getBoundingClientRect();
    const origin = center(home);
    const start = center(from);
    const end = center(to);

    const ghost = button.cloneNode(true) as HTMLElement;
    ghost.classList.remove('invisible');
    Object.assign(ghost.style, {
        position: 'fixed',
        left: `${home.left}px`,
        top: `${home.top}px`,
        width: `${home.width}px`,
        height: `${home.height}px`,
        margin: '0',
        zIndex: '200',
        pointerEvents: 'none',
        transition: 'none',
    });
    document.body.appendChild(ghost);
    inFlight.value[destination] = true;

    const land = () => {
        ghost.remove();
        delete inFlight.value[destination];
    };
    ghost
        .animate(
            [
                { transform: `translate(${start.x - origin.x}px, ${start.y - origin.y}px)` },
                { transform: `translate(${end.x - origin.x}px, ${end.y - origin.y}px)` },
            ],
            { duration: FLIGHT_MS, easing: 'cubic-bezier(0.3, 0.7, 0.3, 1)' },
        )
        .finished.then(land, land);
}

const gapElement = (gapOrder: number) => textRef.value?.querySelector<HTMLElement>(`[data-gap="${gapOrder}"]`) ?? null;

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

async function selectWord(optionIndex: number) {
    if (isVerified.value || usedOptions.value.has(optionIndex)) return;
    const gapOrder = nextEmptyGapOrder();
    if (gapOrder === null) return;

    const from = optionSlots.get(optionIndex)?.getBoundingClientRect();
    userSelections.value[gapOrder] = props.segment.options[optionIndex];
    selectedOption.value[gapOrder] = optionIndex;

    // A palavra sai do banco e vai até a lacuna que ela preenche
    inFlight.value[`gap-${gapOrder}`] = true;
    await nextTick();
    delete inFlight.value[`gap-${gapOrder}`];
    const to = gapElement(gapOrder)?.getBoundingClientRect();
    if (from && to) fly(optionIndex, from, to, `gap-${gapOrder}`);
}

async function unselectGap(gapOrder: number) {
    if (isVerified.value) return;
    const optionIndex = selectedOption.value[gapOrder];
    const from = gapElement(gapOrder)?.getBoundingClientRect();

    delete userSelections.value[gapOrder];
    delete selectedOption.value[gapOrder];
    if (optionIndex === undefined || !from) return;

    // A palavra volta para o lugar dela no banco
    inFlight.value[`option-${optionIndex}`] = true;
    await nextTick();
    delete inFlight.value[`option-${optionIndex}`];
    const to = optionSlots.get(optionIndex)?.getBoundingClientRect();
    if (to) fly(optionIndex, from, to, `option-${optionIndex}`);
}

function clearAll() {
    if (isVerified.value) return;
    userSelections.value = {};
    selectedOption.value = {};
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
    selectedOption.value = {};
    isVerified.value = false;
    verificationResults.value = [];
}

defineExpose({ reset });
</script>

<template>
    <div class="relative py-6">
        <!-- Texto com lacunas (fica dentro do scroll) -->
        <div ref="textRef" class="font-body text-xl md:text-2xl leading-relaxed text-gray-900 dark:text-white font-medium whitespace-pre-line">
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
                            class="lacuna-filled inline-block cursor-pointer select-none rounded-lg border-2 border-gray-300 bg-white font-bold text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            :class="{ invisible: inFlight[`gap-${token.gapOrder}`] }"
                            :data-gap="token.gapOrder"
                            role="button"
                            :aria-label="`Tirar ${userSelections[token.gapOrder!]} da lacuna`"
                            @click="unselectGap(token.gapOrder!)"
                        >{{ userSelections[token.gapOrder!] }}</span>
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
                <!-- A palavra usada deixa o lugar vazio (cinza) e volta para ele ao sair da lacuna -->
                <span
                    v-for="(word, wi) in segment.options"
                    :key="wi"
                    :ref="(element) => setOptionSlot(wi, element)"
                    class="inline-flex rounded-lg"
                    :class="{ 'bg-gray-200 dark:bg-gray-700': isOptionEmpty(wi) }"
                >
                    <GameButton variant="white" size="sm" :class="{ invisible: isOptionEmpty(wi) }" @click="selectWord(wi)">
                        {{ word }}
                    </GameButton>
                </span>
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
        <div v-else-if="!hideResult" class="text-center py-2">
            <div v-if="segment.total_gaps > 0" class="flex items-center justify-center gap-3">
                <div
                    v-if="!hideResultIcon"
                    class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold"
                    :class="verificationResults.filter(r => r.is_correct).length >= requiredCorrect(segment.total_gaps)
                        ? 'bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400'
                        : 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400'"
                >
                    {{ verificationResults.filter(r => r.is_correct).length >= requiredCorrect(segment.total_gaps) ? '✓' : '✗' }}
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
                <!-- A palavra usada deixa o lugar vazio (cinza) e volta para ele ao sair da lacuna -->
                <span
                    v-for="(word, wi) in segment.options"
                    :key="wi"
                    :ref="(element) => setOptionSlot(wi, element)"
                    class="inline-flex rounded-lg"
                    :class="{ 'bg-gray-200 dark:bg-gray-700': isOptionEmpty(wi) }"
                >
                    <GameButton variant="white" size="sm" :class="{ invisible: isOptionEmpty(wi) }" @click="selectWord(wi)">
                        {{ word }}
                    </GameButton>
                </span>
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

/* A palavra na lacuna é o mesmo botão do banco (face, borda e a borda de baixo "3D"), só
   que na medida do texto: letra um pouco menor e borda fina, para caber na linha sem
   empurrar as de cima e de baixo. As cores vêm das mesmas classes do GameButton branco. */
.lacuna-filled {
    --edge: theme('colors.gray.300');
    margin: 0 0.15em;
    padding: 0 0.45em;
    font-size: 0.85em;
    line-height: 1.35;
    vertical-align: baseline;
    box-shadow: 0 3px 0 var(--edge);
    transition:
        transform 0.1s ease,
        box-shadow 0.1s ease;
}

.dark .lacuna-filled {
    --edge: theme('colors.gray.600');
}

/* Afunda ao toque, como o botão: é assim que a palavra volta para o banco */
.lacuna-filled:active {
    transform: translateY(3px);
    box-shadow: 0 0 0 var(--edge);
}
</style>
