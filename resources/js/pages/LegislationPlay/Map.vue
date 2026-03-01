<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Book, FileText, Bookmark, CheckCircle, Star, Lock } from 'lucide-vue-next';
import type { BetaPhase, BetaMapLegislation, PlayUserData, LoadMorePhasesResponse } from '@/types/legislation-play';
import axios from 'axios';

const props = defineProps<{
    phases: BetaPhase[];
    legislations: BetaMapLegislation[];
    selectedLegislationUuids: string[];
    currentPhaseId: number | null;
    hasMoreAbove: boolean;
    hasMoreBelow: boolean;
    totalPhases: number;
    user: PlayUserData;
}>();

const page = usePage<{
    auth: {
        user: {
            id: number;
            name: string;
            is_admin: boolean;
        } | null;
    };
}>();

// Estado reativo
const phases = ref<BetaPhase[]>([...props.phases]);
const hasMoreAbove = ref(props.hasMoreAbove);
const hasMoreBelow = ref(props.hasMoreBelow);
const isLoadingAbove = ref(false);
const isLoadingBelow = ref(false);

// Refs do DOM
const scrollContainerRef = ref<HTMLElement | null>(null);
const topSentinelRef = ref<HTMLElement | null>(null);
const bottomSentinelRef = ref<HTMLElement | null>(null);

// Observers
let topObserver: IntersectionObserver | null = null;
let bottomObserver: IntersectionObserver | null = null;

// === Layout constants ===
const PHASE_SIZE = { current: 90, complete: 64, blocked: 58, default: 62 } as const;
const TRAIL_WIDTH = 200;
const VERTICAL_SPACING = 100;
const X_PATTERN = [0, 35, 70, 105, 70, 35];
const ROAD_STROKE_WIDTH = 16;

// Responsividade
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const updateWindowWidth = () => { windowWidth.value = window.innerWidth; };

const scaleFactor = computed(() => {
    if (windowWidth.value < 400) return 0.7;
    if (windowWidth.value < 640) return 0.82;
    return 1;
});

const scaledTrailWidth = computed(() => TRAIL_WIDTH * scaleFactor.value);
const scaledXPattern = computed(() => X_PATTERN.map(x => x * scaleFactor.value));
const scaledVerticalSpacing = computed(() => VERTICAL_SPACING * scaleFactor.value);
const scaledPhaseSize = computed(() => ({
    current: PHASE_SIZE.current * scaleFactor.value,
    complete: PHASE_SIZE.complete * scaleFactor.value,
    blocked: PHASE_SIZE.blocked * scaleFactor.value,
    default: PHASE_SIZE.default * scaleFactor.value,
}));

// Coordenadas absolutas de cada fase
const phasePositions = computed(() => {
    return phases.value.map((_phase, i) => {
        const patternIdx = i % scaledXPattern.value.length;
        const x = scaledXPattern.value[patternIdx] + scaledPhaseSize.value.current / 2;
        const y = i * scaledVerticalSpacing.value + scaledVerticalSpacing.value;
        return { x, y };
    });
});

// Altura total da trilha
const trailHeight = computed(() => {
    if (phases.value.length === 0) return 0;
    return phases.value.length * scaledVerticalSpacing.value + scaledVerticalSpacing.value * 2;
});

// SVG path da estrada
const roadPath = computed((): string => {
    const positions = phasePositions.value;
    if (positions.length < 2) return '';

    let d = `M ${positions[0].x} ${positions[0].y}`;
    for (let i = 1; i < positions.length; i++) {
        const prev = positions[i - 1];
        const curr = positions[i];
        // Control points ficam mais perto do Y de cada fase para curvar mais
        const cp1y = prev.y + (curr.y - prev.y) * 0.7;
        const cp2y = prev.y + (curr.y - prev.y) * 0.3;
        d += ` C ${prev.x} ${cp1y}, ${curr.x} ${cp2y}, ${curr.x} ${curr.y}`;
    }
    return d;
});

// Tamanho da fase por estado
const getPhaseSize = (phase: BetaPhase): number => {
    if (phase.is_current) return scaledPhaseSize.value.current;
    if (phase.is_complete) return scaledPhaseSize.value.complete;
    if (phase.is_blocked) return scaledPhaseSize.value.blocked;
    return scaledPhaseSize.value.default;
};

// Circunferência para anel de progresso da fase atual
const progressCircumference = 2 * Math.PI * 44;

// Ícones rotativos por fase
const getPhaseIcon = (phaseId: number) => {
    const icons = [Book, FileText, Bookmark, Star, CheckCircle];
    return icons[(phaseId - 1) % icons.length];
};

// Infinite scroll — carregar mais fases abaixo
async function loadMoreBelow() {
    if (isLoadingBelow.value || !hasMoreBelow.value || phases.value.length === 0) return;
    isLoadingBelow.value = true;

    try {
        const lastId = phases.value[phases.value.length - 1]?.id;
        const { data } = await axios.get<LoadMorePhasesResponse>(route('beta.map.phases'), {
            params: { direction: 'below', cursor: lastId, limit: 20 },
        });

        if (data.phases.length > 0) {
            phases.value.push(...data.phases);
            hasMoreBelow.value = data.hasMore;
        } else {
            hasMoreBelow.value = false;
        }
    } catch {
        // Silently fail
    } finally {
        isLoadingBelow.value = false;
    }
}

// Infinite scroll — carregar mais fases acima
async function loadMoreAbove() {
    if (isLoadingAbove.value || !hasMoreAbove.value || phases.value.length === 0) return;
    isLoadingAbove.value = true;

    const container = scrollContainerRef.value!;
    const oldScrollHeight = container.scrollHeight;

    try {
        const firstId = phases.value[0]?.id;
        const { data } = await axios.get<LoadMorePhasesResponse>(route('beta.map.phases'), {
            params: { direction: 'above', cursor: firstId, limit: 20 },
        });

        if (data.phases.length > 0) {
            phases.value.unshift(...data.phases);
            hasMoreAbove.value = data.hasMore;

            // Preservar posição do scroll
            await nextTick();
            container.scrollTop += container.scrollHeight - oldScrollHeight;
        } else {
            hasMoreAbove.value = false;
        }
    } catch {
        // Silently fail
    } finally {
        isLoadingAbove.value = false;
    }
}

// Auto-scroll para a fase atual
const scrollToCurrentPhase = () => {
    if (!props.currentPhaseId) return;
    const el = document.querySelector(`[data-phase-id="${props.currentPhaseId}"]`);
    if (el) {
        el.scrollIntoView({ block: 'center', inline: 'center' });
    }
};

// Verificar se fase atual está visível
const hasCurrentPhaseInView = computed(() => {
    if (!props.currentPhaseId) return false;
    return phases.value.some(p => p.id === props.currentPhaseId && p.is_current);
});

const showScrollButton = ref(false);

const goToCurrentPhase = () => {
    if (hasCurrentPhaseInView.value) {
        scrollToCurrentPhase();
    } else {
        router.visit(route('beta.play.map'));
    }
};

onMounted(() => {
    window.addEventListener('resize', updateWindowWidth);

    // Auto-scroll para fase atual
    nextTick(() => {
        setTimeout(() => scrollToCurrentPhase(), 300);
    });

    // Observer para scroll para baixo
    if (bottomSentinelRef.value) {
        bottomObserver = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMoreBelow.value && !isLoadingBelow.value) {
                    loadMoreBelow();
                }
            },
            { threshold: 0.1 },
        );
        bottomObserver.observe(bottomSentinelRef.value);
    }

    // Observer para scroll para cima
    if (topSentinelRef.value) {
        topObserver = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMoreAbove.value && !isLoadingAbove.value) {
                    loadMoreAbove();
                }
            },
            { threshold: 0.1 },
        );
        topObserver.observe(topSentinelRef.value);
    }

    // Mostrar botão de scroll se fase atual não está visível
    if (!hasCurrentPhaseInView.value && props.currentPhaseId) {
        showScrollButton.value = true;
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', updateWindowWidth);
    topObserver?.disconnect();
    bottomObserver?.disconnect();
});
</script>

<template>
    <Head title="Mapa de Estudos" />

    <AppLayout>
        <div ref="scrollContainerRef" class="container py-6 px-4">
            <div class="max-w-4xl mx-auto">

                <!-- Botão flutuante "Ir para fase atual" -->
                <div v-if="showScrollButton" class="fixed bottom-20 left-4 z-50">
                    <button
                        @click="goToCurrentPhase"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-full shadow-lg transition-all duration-200 hover:scale-105 flex items-center gap-2 font-medium text-sm"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                        <span>Fase atual</span>
                    </button>
                </div>

                <!-- Sentinel topo para infinite scroll -->
                <div ref="topSentinelRef" class="h-1" />

                <!-- Loading indicator topo -->
                <div v-if="isLoadingAbove" class="flex justify-center py-4">
                    <div class="h-6 w-6 border-2 border-gray-300 dark:border-gray-600 border-t-blue-500 rounded-full animate-spin" />
                </div>

                <!-- Estado vazio -->
                <div v-if="phases.length === 0" class="text-center py-16">
                    <Book class="h-16 w-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                    <h2 class="text-xl font-bold text-gray-500 dark:text-gray-400 mb-2">
                        Nenhuma fase disponível
                    </h2>
                    <p class="text-gray-400 dark:text-gray-500">
                        Selecione legislações para estudar.
                    </p>
                </div>

                <!-- Trilha de fases -->
                <div
                    v-else
                    class="trail-path mx-auto relative"
                    :style="{ width: `${scaledTrailWidth}px`, height: `${trailHeight}px` }"
                >
                    <!-- SVG Estrada -->
                    <svg
                        v-if="phasePositions.length >= 2"
                        class="absolute top-0 left-0 w-full pointer-events-none"
                        :height="trailHeight"
                        :viewBox="`0 0 ${scaledTrailWidth} ${trailHeight}`"
                        preserveAspectRatio="xMidYMid meet"
                    >
                        <!-- Estrada principal -->
                        <path
                            :d="roadPath"
                            fill="none"
                            class="road-main"
                            :stroke-width="ROAD_STROKE_WIDTH * scaleFactor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>

                    <!-- Fases -->
                    <template v-for="(phase, phaseIndex) in phases" :key="`phase-${phase.id}`">

                        <!-- Header sutil da legislação -->
                        <div
                            v-if="phase.show_legislation_header"
                            class="absolute left-1/2 -translate-x-1/2 flex items-center gap-3 z-20"
                            :style="{
                                top: `${phasePositions[phaseIndex].y - scaledVerticalSpacing / 2 - 10}px`,
                                width: '280px',
                            }"
                        >
                            <div class="flex-1 h-px bg-gray-300 dark:bg-gray-600"></div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap px-2 bg-gray-50 dark:bg-gray-900">
                                {{ phase.legislation_title }}
                            </span>
                            <div class="flex-1 h-px bg-gray-300 dark:bg-gray-600"></div>
                        </div>

                        <!-- Fase (círculo) -->
                        <div
                            :data-phase-id="phase.id"
                            class="phase-item absolute"
                            :style="{
                                left: `${phasePositions[phaseIndex].x}px`,
                                top: `${phasePositions[phaseIndex].y}px`,
                                transform: 'translate(-50%, -50%)',
                                width: `${getPhaseSize(phase)}px`,
                                height: `${getPhaseSize(phase)}px`,
                            }"
                        >
                            <Link
                                :href="phase.is_blocked ? '#' : route('beta.play.phase', { phaseId: phase.id })"
                                class="relative group transition-transform duration-300 block w-full h-full"
                                :class="{
                                    'cursor-not-allowed': phase.is_blocked,
                                    'hover:scale-110': !phase.is_blocked,
                                    'cursor-pointer': !phase.is_blocked
                                }"
                                @click="phase.is_blocked ? $event.preventDefault() : null"
                            >
                                <!-- === FASE ATUAL === -->
                                <div
                                    v-if="phase.is_current"
                                    class="relative flex items-center justify-center w-full h-full"
                                >
                                    <!-- Anel de progresso (levemente deslocado para baixo) -->
                                    <svg class="absolute w-full h-full" style="top: 4px;" viewBox="0 0 100 100">
                                        <!-- Track cinza do anel -->
                                        <circle
                                            cx="50" cy="50" r="44"
                                            fill="none"
                                            class="stroke-gray-200 dark:stroke-gray-500"
                                            stroke-width="7"
                                        />
                                        <!-- Progresso azul -->
                                        <circle
                                            cx="50" cy="50" r="44"
                                            fill="none"
                                            class="stroke-blue-500"
                                            stroke-width="7"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressCircumference"
                                            :stroke-dashoffset="progressCircumference * (1 - phase.progress.percentage / 100)"
                                            style="transition: stroke-dashoffset 0.6s ease; transform: rotate(-90deg); transform-origin: 50% 50%;"
                                        />
                                    </svg>
                                    <!-- Círculo azul com ícone play -->
                                    <div class="phase-circle-current rounded-full bg-blue-500 flex items-center justify-center z-10"
                                        :style="{ width: `${getPhaseSize(phase) * 0.68}px`, height: `${getPhaseSize(phase) * 0.68}px` }"
                                    >
                                        <svg class="w-6 h-6 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- === FASE COMPLETA === -->
                                <div
                                    v-else-if="phase.is_complete"
                                    class="relative flex items-center justify-center w-full h-full"
                                >
                                    <!-- Base 3D -->
                                    <div class="absolute w-full h-full rounded-full bg-green-700 dark:bg-green-800" style="top: 6px;"></div>
                                    <div class="relative w-full h-full rounded-full bg-green-500 flex items-center justify-center">
                                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- === FASE BLOQUEADA === -->
                                <div
                                    v-else-if="phase.is_blocked"
                                    class="relative flex items-center justify-center w-full h-full"
                                >
                                    <!-- Base 3D -->
                                    <div class="absolute w-full h-full rounded-full bg-gray-400 dark:bg-gray-700" style="top: 5px;"></div>
                                    <div class="relative w-full h-full rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                        <Lock class="w-6 h-6 text-gray-500 dark:text-gray-400" />
                                    </div>
                                </div>

                                <!-- === FASE PADRÃO (em progresso, não atual) === -->
                                <div
                                    v-else
                                    class="relative flex items-center justify-center w-full h-full"
                                >
                                    <!-- Base 3D -->
                                    <div class="absolute w-full h-full rounded-full bg-yellow-700 dark:bg-yellow-800" style="top: 6px;"></div>
                                    <div class="relative w-full h-full rounded-full bg-yellow-500 flex items-center justify-center">
                                        <component :is="getPhaseIcon(phase.id)" class="w-6 h-6 text-white" />
                                    </div>
                                </div>

                                <!-- Balão "Começar!" ACIMA da fase atual -->
                                <div
                                    v-if="phase.is_current && !phase.is_blocked"
                                    class="absolute left-1/2 -translate-x-1/2 z-50 animate-bounce-slow"
                                    :style="{ bottom: `${getPhaseSize(phase) * 0.5 + 32}px` }"
                                >
                                    <div class="relative bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-600 font-bold text-sm whitespace-nowrap">
                                        Começar!
                                        <!-- Seta apontando para baixo -->
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0
                                            border-l-[8px] border-r-[8px] border-t-[10px]
                                            border-l-transparent border-r-transparent border-t-white dark:border-t-gray-800"></div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </template>
                </div>

                <!-- Loading indicator baixo -->
                <div v-if="isLoadingBelow" class="flex justify-center py-4">
                    <div class="h-6 w-6 border-2 border-gray-300 dark:border-gray-600 border-t-blue-500 rounded-full animate-spin" />
                </div>

                <!-- Sentinel baixo para infinite scroll -->
                <div ref="bottomSentinelRef" class="h-1" />

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Estrada — cores com suporte a dark mode */
.road-main {
    stroke: #e5e7eb;
}
:root.dark .road-main,
.dark .road-main {
    stroke: #374151;
}

/* Fase atual — inner circle com sombra 3D sólida */
.phase-circle-current {
    box-shadow: 0 5px 0 0 #1e40af;
}

/* Item da fase */
.phase-item {
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    z-index: 10;
}

/* Animação de bounce lento para o balão */
@keyframes bounce-slow {
    0%, 20%, 53%, 80%, 100% {
        animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
        transform: translate(-50%, 0);
    }
    40%, 43% {
        animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
        transform: translate(-50%, -8px);
    }
    70% {
        animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
        transform: translate(-50%, -4px);
    }
    90% {
        transform: translate(-50%, -2px);
    }
}

.animate-bounce-slow {
    animation: bounce-slow 2s infinite;
}
</style>
