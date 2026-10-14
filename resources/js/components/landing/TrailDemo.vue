<script setup lang="ts">
import { prefersReducedMotion } from '@/composables/useLandingMotion';
import { Check, Lock, Play, RotateCcw, Star } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * A trilha do mapa sendo percorrida conforme a página rola: o caminho vai se
 * pintando e as fases destravam uma a uma. Mesma lógica do jogo — leis
 * intercaladas e uma revisão a cada 3 fases.
 */

interface TrailNode {
    kind: 'phase' | 'review';
    label?: string;
}

const NODES: TrailNode[] = [
    { kind: 'phase', label: 'Constituição Federal' },
    { kind: 'phase' },
    { kind: 'phase' },
    { kind: 'review' },
    { kind: 'phase', label: 'Código Penal' },
    { kind: 'phase' },
    { kind: 'phase' },
    { kind: 'review' },
    { kind: 'phase', label: 'Código Civil' },
    { kind: 'phase' },
];

const WIDTH = 300;
const STEP = 104;
const TOP = 70;
const SWING = [0, 48, 72, 48, 0, -48, -72, -48];

const points = NODES.map((_, i) => ({ x: WIDTH / 2 + SWING[i % SWING.length], y: TOP + i * STEP }));
const height = TOP + (NODES.length - 1) * STEP + 70;

/** Curva suave passando por todos os pontos (Catmull-Rom convertido em Bézier). */
const pathD = (() => {
    let d = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i - 1] ?? points[i];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] ?? p2;
        const c1x = p1.x + (p2.x - p0.x) / 6;
        const c1y = p1.y + (p2.y - p0.y) / 6;
        const c2x = p2.x - (p3.x - p1.x) / 6;
        const c2y = p2.y - (p3.y - p1.y) / 6;
        d += ` C ${c1x} ${c1y}, ${c2x} ${c2y}, ${p2.x} ${p2.y}`;
    }
    return d;
})();

const root = ref<HTMLElement | null>(null);
const pathEl = ref<SVGPathElement | null>(null);
const pathLength = ref(1);
const progress = ref(0);

/** Quantas fases o caminho pintado já alcançou. */
const reached = computed(() => Math.floor(progress.value * (NODES.length - 1) + 0.02));
const currentIndex = computed(() => Math.min(reached.value + 1, NODES.length - 1));

function stateOf(i: number): 'done' | 'current' | 'locked' {
    if (i <= reached.value && !(i === NODES.length - 1 && progress.value < 1)) return 'done';
    if (i === currentIndex.value) return 'current';
    return 'locked';
}

let raf = 0;
function measure() {
    raf = 0;
    const el = root.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    // A "ponta" da tinta acompanha uma linha a 60% da altura da tela: cada fase
    // destrava quando cruza essa linha.
    const head = window.innerHeight * 0.6 - rect.top;
    const first = points[0].y;
    const last = points[points.length - 1].y;
    progress.value = Math.max(0, Math.min(1, (head - first) / (last - first)));
}

function onScroll() {
    if (!raf) raf = requestAnimationFrame(measure);
}

onMounted(() => {
    if (pathEl.value) pathLength.value = pathEl.value.getTotalLength();
    if (prefersReducedMotion()) {
        progress.value = 0.5;
        return;
    }
    measure();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
    cancelAnimationFrame(raf);
});
</script>

<template>
    <div ref="root" class="relative mx-auto" :style="{ width: `${WIDTH}px`, height: `${height}px` }" aria-hidden="true">
        <svg class="absolute inset-0" :width="WIDTH" :height="height" :viewBox="`0 0 ${WIDTH} ${height}`" fill="none">
            <path :d="pathD" class="stroke-gray-200 dark:stroke-gray-700" stroke-width="18" stroke-linecap="round" />
            <path
                ref="pathEl"
                :d="pathD"
                class="stroke-green-400 dark:stroke-green-600"
                stroke-width="18"
                stroke-linecap="round"
                :stroke-dasharray="pathLength"
                :stroke-dashoffset="pathLength * (1 - progress)"
            />
        </svg>

        <template v-for="(node, i) in NODES" :key="i">
            <!-- Divisória da lei, como no mapa -->
            <div
                v-if="node.label"
                class="absolute left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-md border border-gray-200 bg-white px-2.5 py-0.5 text-xs font-semibold text-gray-500 transition-colors duration-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                :class="i <= currentIndex && 'border-green-200 text-green-700 dark:border-green-900 dark:text-green-400'"
                :style="{ top: `${points[i].y - 62}px` }"
            >
                {{ node.label }}
            </div>

            <div class="absolute z-20 -translate-x-1/2 -translate-y-1/2" :style="{ left: `${points[i].x}px`, top: `${points[i].y}px` }">
                <!-- Balão "Começar!" na fase atual -->
                <div
                    v-if="stateOf(i) === 'current'"
                    class="trail-bubble absolute -top-11 left-1/2 whitespace-nowrap rounded-xl border-2 border-gray-200 bg-white px-3 py-1 text-sm font-bold text-blue-600 dark:border-gray-600 dark:bg-gray-800 dark:text-blue-400"
                >
                    Começar!
                </div>
                <span v-if="stateOf(i) === 'current'" class="trail-ring absolute inset-0 rounded-full bg-blue-400/40" />

                <div
                    class="trail-node relative flex h-14 w-14 items-center justify-center rounded-full border-4 text-white"
                    :class="[
                        stateOf(i) === 'done' && node.kind === 'phase' && 'border-green-600 bg-green-500 shadow-[0_6px_0_#15803d]',
                        stateOf(i) === 'done' && node.kind === 'review' && 'border-purple-700 bg-purple-500 shadow-[0_6px_0_#6b21a8]',
                        stateOf(i) === 'current' && 'border-blue-700 bg-blue-500 shadow-[0_6px_0_#1d4ed8]',
                        stateOf(i) === 'locked' &&
                            'border-gray-300 bg-gray-200 text-gray-400 shadow-[0_6px_0_#d4d4d4] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-500 dark:shadow-[0_6px_0_#0f0f0f]',
                    ]"
                    :key="stateOf(i)"
                >
                    <Check v-if="stateOf(i) === 'done' && node.kind === 'phase'" class="h-7 w-7" stroke-width="3" />
                    <Star v-else-if="stateOf(i) === 'done'" class="h-6 w-6 fill-current" />
                    <Play v-else-if="stateOf(i) === 'current'" class="ml-0.5 h-6 w-6 fill-current" />
                    <RotateCcw v-else-if="node.kind === 'review'" class="h-6 w-6" stroke-width="2.5" />
                    <Lock v-else class="h-6 w-6" stroke-width="2.5" />
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.trail-node {
    animation: node-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes node-pop {
    0% {
        transform: scale(0.7);
    }
    100% {
        transform: scale(1);
    }
}

.trail-ring {
    animation: ring 1.6s ease-out infinite;
}

@keyframes ring {
    0% {
        transform: scale(1);
        opacity: 0.9;
    }
    100% {
        transform: scale(1.9);
        opacity: 0;
    }
}

.trail-bubble {
    transform: translateX(-50%);
    animation: bubble-bob 1.4s ease-in-out infinite;
}

.trail-bubble::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -7px;
    width: 12px;
    height: 12px;
    transform: translateX(-50%) rotate(45deg);
    background: inherit;
    border-right: 2px solid;
    border-bottom: 2px solid;
    border-color: inherit;
}

@keyframes bubble-bob {
    0%,
    100% {
        transform: translate(-50%, 0);
    }
    50% {
        transform: translate(-50%, -5px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .trail-node,
    .trail-ring,
    .trail-bubble {
        animation: none;
    }
}
</style>
