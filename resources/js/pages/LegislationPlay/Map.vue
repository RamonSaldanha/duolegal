<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Book, FileText, Bookmark, CheckCircle, Star, Lock, Settings } from 'lucide-vue-next';
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

// Ícones rotativos por fase
const getPhaseIcon = (phaseId: number) => {
    const icons = [Book, FileText, Bookmark, Star, CheckCircle];
    return icons[(phaseId - 1) % icons.length];
};

// Verificar se fase está completa
const isPhaseComplete = (phase: BetaPhase): boolean => {
    return phase.is_complete;
};

// Padrão zigzag: 4 fases indo para direita, 4 voltando para esquerda
const getPhaseXPosition = (globalIndex: number): number => {
    const pattern = [0, 35, 70, 105, 70, 35];
    return pattern[globalIndex % pattern.length];
};

// SVG Progress Ring
const getSegmentDashArray = (totalSegments: number): string => {
    const circumference = 2 * Math.PI * 32;
    const segmentLength = circumference / totalSegments;
    const gapLength = Math.max(3, segmentLength * 0.25);
    const strokeLength = segmentLength - gapLength;
    return `${strokeLength} ${circumference - strokeLength}`;
};

const getSegmentDashOffset = (totalSegments: number, segmentIndex: number): number => {
    const circumference = 2 * Math.PI * 32;
    const segmentLength = circumference / totalSegments;
    return circumference - (segmentLength * segmentIndex);
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

// Responsividade
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const updateWindowWidth = () => { windowWidth.value = window.innerWidth; };

// Índice global para o zigzag (contínuo através de todas as fases)
const getGlobalPhaseIndex = (phaseIndex: number): number => {
    return phaseIndex;
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

                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                            Mapa de Estudos
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ totalPhases }} fases
                        </p>
                    </div>
                    <Link
                        :href="route('beta.preferences')"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        <Settings class="h-4 w-4" />
                        <span class="hidden sm:inline">Legislações</span>
                    </Link>
                </div>

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
                <div v-else class="trail-path mx-auto flex flex-col">
                    <template v-for="(phase, phaseIndex) in phases" :key="`phase-${phase.id}`">

                        <!-- Header sutil da legislação -->
                        <div
                            v-if="phase.show_legislation_header"
                            class="flex items-center gap-3 my-6 w-full"
                            :style="{ marginLeft: '-20px', width: 'calc(100% + 40px)' }"
                        >
                            <div class="flex-1 h-px bg-gray-300 dark:bg-gray-600"></div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap px-2">
                                {{ phase.legislation_title }}
                            </span>
                            <div class="flex-1 h-px bg-gray-300 dark:bg-gray-600"></div>
                        </div>

                        <!-- Fase (círculo) -->
                        <div
                            :data-phase-id="phase.id"
                            class="phase-item"
                            :style="{
                                transform: `translateX(${getPhaseXPosition(getGlobalPhaseIndex(phaseIndex))}px)`,
                                marginBottom: '10px'
                            }"
                        >
                            <Link
                                :href="phase.is_blocked ? '#' : route('beta.play.phase', { phaseId: phase.id })"
                                class="relative group transition-transform duration-300 block phase-link"
                                :class="{
                                    'cursor-not-allowed': phase.is_blocked,
                                    'hover:scale-110': !phase.is_blocked,
                                    'cursor-pointer': !phase.is_blocked
                                }"
                                @click="phase.is_blocked ? $event.preventDefault() : null"
                            >
                                <!-- Container com progresso circular -->
                                <div class="relative flex items-center justify-center w-18 h-18">
                                    <!-- SVG Progress Ring -->
                                    <svg
                                        v-if="phase.progress.block_status.length > 0"
                                        class="absolute w-18 h-18 transform -rotate-90"
                                        viewBox="0 0 72 72"
                                    >
                                        <!-- Background segments -->
                                        <circle
                                            v-for="(status, segIdx) in phase.progress.block_status"
                                            :key="`bg-${phase.id}-${segIdx}`"
                                            cx="36" cy="36" r="32"
                                            fill="none"
                                            stroke="rgba(200,200,200,0.4)"
                                            stroke-width="3"
                                            stroke-linecap="round"
                                            :stroke-dasharray="getSegmentDashArray(phase.progress.block_status.length)"
                                            :stroke-dashoffset="getSegmentDashOffset(phase.progress.block_status.length, segIdx)"
                                            :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                        />
                                        <!-- Colored segments -->
                                        <circle
                                            v-for="(status, segIdx) in phase.progress.block_status"
                                            :key="`seg-${phase.id}-${segIdx}`"
                                            cx="36" cy="36" r="32"
                                            fill="none"
                                            :stroke="status === 'correct' ? '#22c55e' : status === 'incorrect' ? '#ef4444' : 'transparent'"
                                            stroke-width="3"
                                            stroke-linecap="round"
                                            :stroke-dasharray="getSegmentDashArray(phase.progress.block_status.length)"
                                            :stroke-dashoffset="getSegmentDashOffset(phase.progress.block_status.length, segIdx)"
                                            :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                        />
                                    </svg>

                                    <!-- Bolinha da fase -->
                                    <div
                                        :class="[
                                            'w-16 h-16 rounded-full flex items-center justify-center phase-circle relative z-10',
                                            isPhaseComplete(phase) ? 'bg-green-500' :
                                            phase.is_current ? 'bg-blue-500 animate-pulse' :
                                            phase.is_blocked ? 'bg-gray-400/50' :
                                            'bg-yellow-500'
                                        ]"
                                        :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                    >
                                        <component
                                            :is="isPhaseComplete(phase) ? CheckCircle : phase.is_blocked ? Lock : getPhaseIcon(phase.id)"
                                            class="w-6 h-6 text-white"
                                        />
                                    </div>
                                </div>

                                <!-- Balão "Começar!" para fase atual -->
                                <div
                                    v-if="phase.is_current && !phase.is_blocked"
                                    class="absolute -top-7 left-1/2 transform -translate-x-1/2 z-30 animate-bounce-slow"
                                >
                                    <div class="relative bg-white text-gray-600 px-3 py-2 rounded-lg shadow-lg border-1 border-gray-500 font-bold text-sm whitespace-nowrap">
                                        Começar!
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-6 border-r-6 border-t-8 border-l-transparent border-r-transparent border-t-gray-500"></div>
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-[-1px] w-0 h-0 border-l-5 border-r-5 border-t-7 border-l-transparent border-r-transparent border-t-white"></div>
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
/* Tamanho 18 (72px) */
.w-18 {
    width: 4.5rem;
}
.h-18 {
    height: 4.5rem;
}

/* Animação de bounce lento */
@keyframes bounce-slow {
    0%, 20%, 53%, 80%, 100% {
        animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
        transform: translate3d(-50%, 0, 0);
    }
    40%, 43% {
        animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
        transform: translate3d(-50%, -8px, 0);
    }
    70% {
        animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
        transform: translate3d(-50%, -4px, 0);
    }
    90% {
        transform: translate3d(-50%, -2px, 0);
    }
}

.animate-bounce-slow {
    animation: bounce-slow 2s infinite;
}

/* Setas do balão */
.border-t-8 { border-top-width: 8px; }
.border-t-7 { border-top-width: 7px; }
.border-l-6 { border-left-width: 6px; }
.border-r-6 { border-right-width: 6px; }
.border-l-5 { border-left-width: 5px; }
.border-r-5 { border-right-width: 5px; }

/* Bolinha com efeito 3D */
.phase-circle {
    box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.2), 0 3px 4px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
    width: 3.5rem !important;
    height: 3.5rem !important;
}

.phase-circle svg {
    width: 1.25rem !important;
    height: 1.25rem !important;
}

/* Container da trilha */
.trail-path {
    position: relative;
    padding: 20px 0;
    width: 170px; /* 105px max offset + ~64px circle */
    margin: 0 auto;
}

/* Item da fase */
.phase-item {
    position: relative;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.phase-item:last-child {
    margin-bottom: 0 !important;
}

.phase-link {
    position: relative;
    z-index: 10;
    display: inline-block;
}

/* Responsividade mobile */
@media (max-width: 640px) {
    .trail-path {
        width: 140px; /* Proporcionalmente menor */
    }

    /* Escalar posições para mobile */
    .phase-item[style*="translateX(35px)"] { transform: translateX(25px) !important; }
    .phase-item[style*="translateX(70px)"] { transform: translateX(50px) !important; }
    .phase-item[style*="translateX(105px)"] { transform: translateX(75px) !important; }
}
</style>
