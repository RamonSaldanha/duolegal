<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Book, Check, Lock, RotateCcw } from 'lucide-vue-next';
import WelcomeOfferBanner from '@/components/WelcomeOfferBanner.vue';
import type { BetaGroup, BetaSection } from '@/types/beta-play';

const props = defineProps<{
    sections: BetaSection[];
    hasMoreAbove: boolean;
    hasMoreBelow: boolean;
    window: { before: number; after: number };
    phasesPerGroup: number;
}>();

// === Grade ===
// Tudo é medido em "U". A trilha tem 6 colunas de largura U; os quadrados ficam na
// esquerda, no centro ou na direita e descem uma linha por vez:
// centro → direita → centro → esquerda.
const COLUMNS = 6;
const MAX_UNIT = 60;
const COLUMN_PATTERN = [3, 5, 3, 1];
// Distância entre um quadrado e o seguinte, em U. Deixa um trecho de caminho livre entre
// eles, onde depois cabe um item extra (um desafio, um prêmio).
const ROW_STEP = 2.6;

const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const updateWindowWidth = () => {
    windowWidth.value = window.innerWidth;
};

const MIN_UNIT = 40;
const unit = computed(() => Math.max(MIN_UNIT, Math.min(MAX_UNIT, Math.floor((windowWidth.value - 32) / COLUMNS))));
const trailWidth = computed(() => unit.value * COLUMNS);
const nodeSize = computed(() => unit.value * 1.5);
// Quadrado atual: a face tem o tamanho dos outros e a moldura acompanha o mesmo contorno,
// com uma folga igual em toda a volta (a de baixo inclui a "borda de baixo" da face).
const FRAME_GAP = 10;
const FACE_DEPTH = 6;
const faceRadius = computed(() => nodeSize.value * 0.24);
const pathWidth = computed(() => unit.value * 0.2);

interface Point {
    x: number;
    y: number;
}

function positions(count: number): Point[] {
    return Array.from({ length: count }, (_, i) => ({
        x: COLUMN_PATTERN[i % COLUMN_PATTERN.length] * unit.value,
        y: unit.value * 1.5 + i * unit.value * ROW_STEP,
    }));
}

function trailHeight(count: number): number {
    return unit.value * 3 + (count - 1) * unit.value * ROW_STEP;
}

/**
 * Caminho em cotovelo entre dois quadrados: saindo do centro, anda de lado e depois
 * desce; saindo de uma lateral, desce e depois anda de lado.
 */
function connector(from: Point, to: Point, fromIndex: number): string {
    const r = unit.value * 0.5;
    const dir = to.x > from.x ? 1 : -1;

    if (fromIndex % 2 === 0) {
        return `M ${from.x} ${from.y} H ${to.x - dir * r} Q ${to.x} ${from.y} ${to.x} ${from.y + r} V ${to.y}`;
    }
    return `M ${from.x} ${from.y} V ${to.y - r} Q ${from.x} ${to.y} ${from.x + dir * r} ${to.y} H ${to.x}`;
}

// A trilha é uma só: os quadrados de todas as etapas seguem ligados, sem quebra entre
// uma etapa e outra. O que muda de etapa para etapa é só o título do cartão no topo.
const flatGroups = computed(() => props.sections.flatMap((section) => section.groups));
const sectionOfGroup = computed(() => props.sections.flatMap((section, si) => section.groups.map(() => si)));

const trail = computed(() => {
    const points = positions(flatGroups.value.length);
    return {
        points,
        height: trailHeight(flatGroups.value.length),
        connectors: points.slice(0, -1).map((point, i) => ({
            d: connector(point, points[i + 1], i),
            done: flatGroups.value[i].state === 'complete',
        })),
    };
});

// Etapa mostrada no cartão: a do quadrado mais próximo do meio da tela
const cardRef = ref<HTMLElement | null>(null);
const trailRef = ref<HTMLElement | null>(null);
const shownSectionIndex = ref(Math.max(0, props.sections.findIndex((section) => section.is_current)));
const shownSection = computed(() => props.sections[shownSectionIndex.value] ?? props.sections[0] ?? null);

function updateShownSection() {
    if (!cardRef.value || !trailRef.value || flatGroups.value.length === 0) return;

    // Meio da área visível da trilha (entre o cartão e o fim da tela): é onde o quadrado
    // atual fica ao abrir, então o cartão já abre com a etapa em que a pessoa está.
    const middle = (cardRef.value.getBoundingClientRect().bottom + window.innerHeight) / 2;
    const y = middle - trailRef.value.getBoundingClientRect().top;
    const nearest = Math.round((y - unit.value * 1.5) / (unit.value * ROW_STEP));
    const row = Math.min(flatGroups.value.length - 1, Math.max(0, nearest));
    shownSectionIndex.value = sectionOfGroup.value[row];
}

// Ao pedir mais trechos, a lista muda e a etapa do topo pode ser outra
watch(
    () => props.sections,
    () => nextTick(updateShownSection),
);

// Anel de progresso do quadrado atual
const RING_RADIUS = 30;
const ringCircumference = 2 * Math.PI * RING_RADIUS;
const ringOffset = (group: BetaGroup) =>
    ringCircumference * (1 - group.completed_phases / Math.max(1, group.total_phases));

// Quadrado concluído aberto (lista de fases para revisar)
const openGroupId = ref<number | null>(null);
const toggleGroup = (group: BetaGroup) => {
    openGroupId.value = openGroupId.value === group.id ? null : group.id;
};

function loadMore(direction: 'above' | 'below') {
    router.get(
        route('beta.play.map'),
        {
            before: props.window.before + (direction === 'above' ? 3 : 0),
            after: props.window.after + (direction === 'below' ? 5 : 0),
        },
        { preserveScroll: direction === 'below', replace: true },
    );
}

// O cartão da etapa gruda no topo ao rolar, abaixo do cabeçalho do app e, quando ela
// aparece, da oferta de boas-vindas (que também gruda). A altura da oferta é medida
// porque ela pode sumir: ao ser fechada ou quando o prazo acaba.
const containerRef = ref<HTMLElement | null>(null);
const offerHeight = ref(0);
let offerWatcher: MutationObserver | null = null;

function measureOffer() {
    const offer = containerRef.value?.querySelector<HTMLElement>('[data-welcome-offer]');
    offerHeight.value = offer ? offer.offsetHeight + 8 : 0;
}

const cardTop = computed(() => `calc(var(--app-header-h, 64px) + ${8 + offerHeight.value}px)`);

let stopNavigateListener: (() => void) | null = null;
let navigateTimer: ReturnType<typeof setTimeout> | undefined;

function scrollToCurrent() {
    const current = document.querySelector('[data-current-group]');
    // No meio da área visível da trilha, o mesmo ponto que escolhe a etapa do cartão.
    // Duas passadas: na primeira o cartão ainda pode não estar parado no topo.
    for (let pass = 0; current && cardRef.value && pass < 2; pass++) {
        const rect = current.getBoundingClientRect();
        const middle = (cardRef.value.getBoundingClientRect().bottom + window.innerHeight) / 2;
        window.scrollBy(0, rect.top + rect.height / 2 - middle);
    }
    updateShownSection();
}

function forgetNavigateListener() {
    clearTimeout(navigateTimer);
    stopNavigateListener?.();
    stopNavigateListener = null;
}

onMounted(async () => {
    window.addEventListener('resize', updateWindowWidth);
    window.addEventListener('resize', measureOffer);
    window.addEventListener('resize', updateShownSection);
    window.addEventListener('scroll', updateShownSection, { passive: true });

    if (containerRef.value) {
        offerWatcher = new MutationObserver(measureOffer);
        offerWatcher.observe(containerRef.value, { childList: true });
    }
    measureOffer();

    // Só na primeira abertura: ao pedir mais trechos, a tela fica onde estava
    if (props.window.before === 0 && props.window.after === 0) {
        await nextTick();
        scrollToCurrent();

        // O Inertia mexe na rolagem logo depois de montar a página (volta ao topo ou, ao
        // recarregar, à posição antiga). Repete quando ele avisa que terminou.
        stopNavigateListener = router.on('navigate', () => {
            scrollToCurrent();
            forgetNavigateListener();
        });
        navigateTimer = setTimeout(forgetNavigateListener, 1000);
    }
    updateShownSection();
});

onUnmounted(() => {
    window.removeEventListener('resize', updateWindowWidth);
    window.removeEventListener('resize', measureOffer);
    window.removeEventListener('resize', updateShownSection);
    window.removeEventListener('scroll', updateShownSection);
    offerWatcher?.disconnect();
    forgetNavigateListener();
});
</script>

<template>
    <Head title="Mapa (beta)" />

    <AppLayout>
        <div class="px-4 py-6">
            <div ref="containerRef" class="mx-auto" :style="{ width: `${trailWidth}px` }">
                <!-- Lembrete da oferta de boas-vindas (24h depois do onboarding), como no mapa atual -->
                <WelcomeOfferBanner />

                <!-- Aviso de teste -->
                <div class="mb-5 flex items-center justify-between text-xs">
                    <span class="rounded-md bg-blue-100 px-2 py-1 font-bold uppercase tracking-wide text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                        Mapa beta
                    </span>
                    <Link :href="route('play.map')" class="font-medium text-gray-500 underline-offset-2 hover:underline dark:text-gray-400">
                        Voltar ao mapa atual
                    </Link>
                </div>

                <!-- Estado vazio -->
                <div v-if="sections.length === 0" class="py-16 text-center">
                    <Book class="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                    <h2 class="mb-2 text-xl font-bold text-gray-500 dark:text-gray-400">Nenhuma fase disponível</h2>
                    <p class="text-gray-400 dark:text-gray-500">Selecione legislações para estudar.</p>
                </div>

                <button v-if="hasMoreAbove" type="button" class="more-button mb-6" @click="loadMore('above')">
                    Ver trechos anteriores
                </button>

                <template v-if="shownSection">
                    <!-- Cartão da etapa: um só, parado no topo; o título muda conforme a etapa que passa por ele -->
                    <div
                        ref="cardRef"
                        class="section-card section-sticky sticky z-20 rounded-2xl border-2 border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800"
                        :style="{ top: cardTop }"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">Etapa {{ shownSection.number }}</p>
                        <h2 class="mt-0.5 truncate text-lg font-bold leading-snug text-gray-900 dark:text-white">
                            {{ shownSection.legislation_title }}
                        </h2>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-full rounded-full bg-green-500"
                                :style="{ width: `${(shownSection.completed_phases / Math.max(1, shownSection.total_phases)) * 100}%` }"
                            />
                        </div>
                    </div>

                    <!-- Trilha -->
                    <div ref="trailRef" class="relative" :style="{ height: `${trail.height}px` }">
                        <svg
                            class="pointer-events-none absolute inset-0"
                            :width="trailWidth"
                            :height="trail.height"
                            :viewBox="`0 0 ${trailWidth} ${trail.height}`"
                        >
                            <path
                                v-for="(line, li) in trail.connectors"
                                :key="li"
                                :d="line.d"
                                fill="none"
                                stroke-linecap="round"
                                :stroke-width="pathWidth"
                                :class="line.done ? 'stroke-green-300 dark:stroke-green-800' : 'stroke-gray-200 dark:stroke-gray-700'"
                            />
                        </svg>

                        <template v-for="(group, gi) in flatGroups" :key="group.id">
                            <!-- Quadrado atual: abre direto a próxima fase -->
                            <Link
                                v-if="group.state === 'current' && group.current_phase_id"
                                :href="route('beta.play.phase', { phaseId: group.current_phase_id })"
                                data-current-group
                                class="node node-current absolute block"
                                :style="{
                                    left: `${trail.points[gi].x}px`,
                                    top: `${trail.points[gi].y}px`,
                                    width: `${nodeSize + FRAME_GAP * 2}px`,
                                    height: `${nodeSize + FRAME_GAP * 2 + FACE_DEPTH}px`,
                                }"
                                :aria-label="`Começar fase ${group.completed_phases + 1} de ${group.total_phases}`"
                            >
                                <span
                                    class="node-frame absolute inset-0 border-4 bg-white dark:bg-gray-900"
                                    :style="{ borderRadius: `${faceRadius + FRAME_GAP}px` }"
                                />
                                <span
                                    class="node-face absolute flex items-center justify-center bg-blue-500"
                                    :style="{
                                        inset: `${FRAME_GAP}px ${FRAME_GAP}px ${FRAME_GAP + FACE_DEPTH}px`,
                                        borderRadius: `${faceRadius}px`,
                                    }"
                                >
                                    <svg class="absolute h-[78%] w-[78%]" viewBox="0 0 80 80">
                                        <circle cx="40" cy="40" :r="RING_RADIUS" fill="none" stroke-width="7" class="stroke-blue-700" />
                                        <circle
                                            cx="40"
                                            cy="40"
                                            :r="RING_RADIUS"
                                            fill="none"
                                            stroke-width="7"
                                            stroke-linecap="round"
                                            class="stroke-white"
                                            :stroke-dasharray="ringCircumference"
                                            :stroke-dashoffset="ringOffset(group)"
                                            transform="rotate(-90 40 40)"
                                        />
                                    </svg>
                                    <span class="relative font-bold text-white" :style="{ fontSize: `${unit * 0.3}px` }">
                                        {{ group.completed_phases }}/{{ group.total_phases }}
                                    </span>
                                </span>
                            </Link>

                            <!-- Quadrado concluído: lista as fases para revisar -->
                            <button
                                v-else-if="group.state === 'complete'"
                                type="button"
                                class="node node-complete absolute"
                                :style="{
                                    left: `${trail.points[gi].x}px`,
                                    top: `${trail.points[gi].y}px`,
                                    width: `${nodeSize}px`,
                                    height: `${nodeSize}px`,
                                }"
                                aria-label="Fases concluídas"
                                @click="toggleGroup(group)"
                            >
                                <span class="node-face absolute inset-0 flex items-center justify-center bg-green-500">
                                    <Check class="h-2/5 w-2/5 text-white" :stroke-width="3.5" />
                                </span>
                            </button>

                            <!-- Quadrado bloqueado -->
                            <div
                                v-else
                                class="node node-locked absolute"
                                :style="{
                                    left: `${trail.points[gi].x}px`,
                                    top: `${trail.points[gi].y}px`,
                                    width: `${nodeSize}px`,
                                    height: `${nodeSize}px`,
                                }"
                                aria-label="Bloqueado"
                            >
                                <span class="node-face absolute inset-0 flex items-center justify-center bg-gray-200 dark:bg-gray-700">
                                    <Lock class="h-1/3 w-1/3 text-gray-400 dark:text-gray-500" :stroke-width="2.5" />
                                </span>
                            </div>

                            <!-- Fases do quadrado concluído -->
                            <template v-if="openGroupId === group.id">
                                <div class="fixed inset-0 z-20" @click="openGroupId = null" />
                                <div
                                    class="section-card absolute left-0 right-0 z-30 rounded-2xl border-2 border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"
                                    :style="{ top: `${trail.points[gi].y + nodeSize / 2 + 16}px` }"
                                >
                                    <Link
                                        v-for="(phase, pi) in group.phases"
                                        :key="phase.id"
                                        :href="route('beta.play.phase', { phaseId: phase.id })"
                                        class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700"
                                    >
                                        <span>Fase {{ pi + 1 }}</span>
                                        <span class="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                                            <RotateCcw class="h-4 w-4" />
                                            Revisar
                                        </span>
                                    </Link>
                                </div>
                            </template>
                        </template>
                    </div>
                </template>

                <button v-if="hasMoreBelow" type="button" class="more-button mt-2" @click="loadMore('below')">
                    Ver mais trechos
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Cartões com a "borda de baixo" sólida, como os quadrados */
.section-card {
    box-shadow: 0 4px 0 0 theme('colors.gray.200');
}
.dark .section-card {
    box-shadow: 0 4px 0 0 theme('colors.gray.700');
}

/* Parado no topo, o cartão cobre a fresta acima dele para a trilha não aparecer ali */
.section-sticky {
    box-shadow:
        0 4px 0 0 theme('colors.gray.200'),
        0 -12px 0 0 hsl(var(--background));
}
.dark .section-sticky {
    box-shadow:
        0 4px 0 0 theme('colors.gray.700'),
        0 -12px 0 0 hsl(var(--background));
}

.node {
    transform: translate(-50%, -50%);
    z-index: 10;
}

.node-face {
    border-radius: 24%;
}

/* Moldura do quadrado atual: pisca devagar para chamar o próximo passo */
.node-frame {
    border-color: theme('colors.blue.400');
    animation: frame-pulse 1.6s ease-in-out infinite;
}
@keyframes frame-pulse {
    0%,
    100% {
        border-color: theme('colors.blue.200');
    }
    50% {
        border-color: theme('colors.blue.500');
    }
}
.dark .node-frame {
    animation-name: frame-pulse-dark;
}
@keyframes frame-pulse-dark {
    0%,
    100% {
        border-color: theme('colors.blue.900');
    }
    50% {
        border-color: theme('colors.blue.400');
    }
}
@media (prefers-reduced-motion: reduce) {
    .node-frame {
        animation: none;
    }
}
.node-face {
    transition:
        transform 0.1s ease,
        box-shadow 0.1s ease;
}

.node-current .node-face {
    box-shadow: 0 6px 0 0 #1d4ed8;
}
.node-complete .node-face {
    box-shadow: 0 6px 0 0 #15803d;
}
.node-locked .node-face {
    box-shadow: 0 6px 0 0 theme('colors.gray.300');
}
.dark .node-locked .node-face {
    box-shadow: 0 6px 0 0 theme('colors.gray.800');
}

/* Apertar: a face desce e a borda de baixo some */
.node-current:hover .node-face,
.node-complete:hover .node-face {
    transform: translateY(2px);
}
.node-current:hover .node-face {
    box-shadow: 0 4px 0 0 #1d4ed8;
}
.node-complete:hover .node-face {
    box-shadow: 0 4px 0 0 #15803d;
}
.node-current:active .node-face,
.node-complete:active .node-face {
    transform: translateY(6px);
    box-shadow: none;
}

.more-button {
    @apply block w-full rounded-xl border-2 border-gray-200 px-4 py-2.5 text-sm font-bold text-gray-500 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800;
}
</style>
