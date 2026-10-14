<script setup lang="ts">
import { prefersReducedMotion, sleep, useInView } from '@/composables/useLandingMotion';
import { Check, Flame, Heart } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Uma fase do jogo se jogando sozinha: as palavras voam do banco para as
 * lacunas, o "Verificar" é apertado, o XP sobe e a ofensiva acende. Em loop,
 * trocando de artigo. Mesmas peças visuais da tela de fase de verdade.
 */

type Segment = string | { gap: number };

interface DemoArticle {
    law: string;
    ref: string;
    segments: Segment[];
    answers: string[];
    /** Ordem em que as palavras aparecem no banco (respostas + distratores). */
    bank: string[];
}

const ARTICLES: DemoArticle[] = [
    {
        law: 'Constituição Federal',
        ref: 'Art. 5º',
        segments: [
            'Todos são ',
            { gap: 0 },
            ' perante a ',
            { gap: 1 },
            ', sem distinção de qualquer natureza, garantindo-se a inviolabilidade do direito à ',
            { gap: 2 },
            ', à ',
            { gap: 3 },
            ', à igualdade, à segurança e à propriedade.',
        ],
        answers: ['iguais', 'lei', 'vida', 'liberdade'],
        bank: ['liberdade', 'União', 'iguais', 'vida', 'crime', 'lei'],
    },
    {
        law: 'Código Penal',
        ref: 'Art. 121',
        segments: [{ gap: 0 }, ' alguém: Pena — ', { gap: 1 }, ', de ', { gap: 2 }, ' a ', { gap: 3 }, ' anos.'],
        answers: ['Matar', 'reclusão', 'seis', 'vinte'],
        bank: ['vinte', 'detenção', 'Matar', 'doze', 'seis', 'reclusão'],
    },
    {
        law: 'Código Civil',
        ref: 'Art. 1º',
        segments: ['Toda ', { gap: 0 }, ' é capaz de ', { gap: 1 }, ' e ', { gap: 2 }, ' na ordem ', { gap: 3 }, '.'],
        answers: ['pessoa', 'direitos', 'deveres', 'civil'],
        bank: ['deveres', 'penal', 'pessoa', 'civil', 'obrigações', 'direitos'],
    },
];

const CONFETTI_COLORS = ['#22c55e', '#3b82f6', '#facc15', '#a855f7', '#f97316', '#ef4444'];

const root = ref<HTMLElement | null>(null);
const inView = useInView(root, { threshold: 0.1 });

const articleIndex = ref(0);
const article = computed(() => ARTICLES[articleIndex.value]);

const filled = ref<boolean[]>([]);
const correct = ref<boolean[]>([]);
const used = ref<Set<string>>(new Set());
const pressedChip = ref<string | null>(null);
const verifyPressed = ref(false);
const success = ref(false);
const fading = ref(false);
const xp = ref(120);
const xpGain = ref(0);
const streak = ref(6);
const streakPop = ref(false);
const burstKey = ref(0);

const chipEls = new Map<string, HTMLElement>();
const gapEls: HTMLElement[] = [];

const filledCount = computed(() => filled.value.filter(Boolean).length);
const progress = computed(() => (success.value ? 100 : (filledCount.value / 4) * 85));
const allFilled = computed(() => filledCount.value === article.value.answers.length);

const confetti = computed(() =>
    Array.from({ length: 16 }, (_, i) => {
        const angle = (Math.PI * 2 * i) / 16 + (i % 2 ? 0.2 : -0.1);
        const dist = 70 + ((i * 37) % 60);
        return {
            color: CONFETTI_COLORS[i % CONFETTI_COLORS.length],
            dx: `${Math.cos(angle) * dist}px`,
            dy: `${Math.sin(angle) * dist - 30}px`,
            rot: `${(i * 67) % 360}deg`,
            round: i % 3 === 0,
        };
    }),
);

function resetRound() {
    filled.value = article.value.answers.map(() => false);
    correct.value = article.value.answers.map(() => false);
    used.value = new Set();
    success.value = false;
    verifyPressed.value = false;
    xpGain.value = 0;
}

function showSolved() {
    filled.value = article.value.answers.map(() => true);
    correct.value = article.value.answers.map(() => true);
    used.value = new Set(article.value.answers);
    success.value = true;
}

let alive = true;

async function waitVisible() {
    while (alive && (!inView.value || document.hidden)) {
        await sleep(250);
    }
}

/** A palavra sai do banco e voa em arco até a lacuna. */
async function flyWord(word: string, gap: number) {
    const chip = chipEls.get(word);
    const target = gapEls[gap];
    const box = root.value;
    if (!chip || !target || !box) {
        filled.value[gap] = true;
        return;
    }

    pressedChip.value = word;
    await sleep(170);
    pressedChip.value = null;

    const b = box.getBoundingClientRect();
    const from = chip.getBoundingClientRect();
    const to = target.getBoundingClientRect();

    const ghost = document.createElement('span');
    ghost.textContent = word;
    ghost.className =
        'pointer-events-none absolute z-30 rounded-xl border-2 border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-800 shadow-[0_4px_0_#d4d4d4] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:shadow-[0_4px_0_#171717]';
    ghost.style.left = `${from.left - b.left}px`;
    ghost.style.top = `${from.top - b.top}px`;
    box.appendChild(ghost);

    used.value = new Set(used.value).add(word);

    const dx = to.left - from.left + (to.width - from.width) / 2;
    const dy = to.top - from.top + (to.height - from.height) / 2;

    const flight = ghost.animate(
        [
            { transform: 'translate(0, 0) rotate(0deg) scale(1)' },
            { transform: `translate(${dx * 0.5}px, ${dy * 0.5 - 46}px) rotate(-6deg) scale(1.08)`, offset: 0.5 },
            { transform: `translate(${dx}px, ${dy}px) rotate(0deg) scale(0.92)` },
        ],
        { duration: 560, easing: 'cubic-bezier(.45,0,.25,1)', fill: 'forwards' },
    );
    await flight.finished.catch(() => undefined);
    ghost.remove();
    filled.value[gap] = true;
}

async function play() {
    while (alive) {
        resetRound();
        fading.value = false;
        await sleep(900);

        const order = article.value.answers.map((word, gap) => ({ word, gap }));
        for (const { word, gap } of order) {
            await waitVisible();
            if (!alive) return;
            await flyWord(word, gap);
            await sleep(260);
        }

        await sleep(350);
        verifyPressed.value = true;
        await sleep(160);
        verifyPressed.value = false;

        for (let i = 0; i < correct.value.length; i++) {
            correct.value[i] = true;
            await sleep(110);
        }

        success.value = true;
        burstKey.value++;
        xpGain.value = 25;
        await sleep(350);
        countXp(xp.value + 25);
        streak.value++;
        streakPop.value = true;
        await sleep(600);
        streakPop.value = false;

        await sleep(2400);
        await waitVisible();
        fading.value = true;
        await sleep(380);
        articleIndex.value = (articleIndex.value + 1) % ARTICLES.length;
        if (streak.value > 30) streak.value = 6;
        await nextTick();
    }
}

let xpRaf = 0;
function countXp(target: number) {
    const start = xp.value;
    const t0 = performance.now();
    const step = (now: number) => {
        const p = Math.min(1, (now - t0) / 700);
        xp.value = Math.round(start + (target - start) * (1 - Math.pow(1 - p, 3)));
        if (p < 1) xpRaf = requestAnimationFrame(step);
    };
    xpRaf = requestAnimationFrame(step);
}

onMounted(() => {
    if (prefersReducedMotion()) {
        showSolved();
        return;
    }
    resetRound();
    play();
});

onBeforeUnmount(() => {
    alive = false;
    cancelAnimationFrame(xpRaf);
});

const setChip = (word: string) => (el: unknown) => {
    if (el) chipEls.set(word, el as HTMLElement);
    else chipEls.delete(word);
};
const setGap = (i: number) => (el: unknown) => {
    if (el) gapEls[i] = el as HTMLElement;
};
</script>

<template>
    <div ref="root" class="relative select-none" aria-hidden="true">
        <div
            class="relative overflow-hidden rounded-3xl border-2 border-gray-200 bg-white shadow-[0_8px_0_#e5e5e5] dark:border-gray-700 dark:bg-gray-800 dark:shadow-[0_8px_0_#0f0f0f]"
        >
            <!-- Barra do topo: progresso + status do jogador -->
            <div class="flex items-center gap-3 border-b-2 border-gray-100 px-4 py-3 dark:border-gray-700">
                <div class="h-3 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="demo-progress h-full rounded-full bg-green-500" :style="{ width: `${progress}%` }" />
                </div>
                <span class="flex items-center gap-1 rounded-full bg-pink-50 px-2 py-1 text-xs font-bold text-pink-500 dark:bg-pink-950/50">
                    <Heart class="h-3.5 w-3.5 fill-current" /> 5
                </span>
                <span
                    class="flex items-center gap-1 rounded-full px-2 py-1 text-xs font-bold transition-colors duration-500"
                    :class="success ? 'bg-orange-50 text-orange-500 dark:bg-orange-950/50' : 'bg-gray-100 text-gray-400 dark:bg-gray-700'"
                >
                    <Flame class="h-3.5 w-3.5" :class="[success && 'fill-current', streakPop && 'streak-pop']" /> {{ streak }}
                </span>
                <span
                    class="relative flex items-center gap-1 rounded-full bg-purple-50 px-2 py-1 text-xs font-bold text-purple-600 dark:bg-purple-950/50 dark:text-purple-300"
                >
                    XP <span class="tabular-nums">{{ xp }}</span>
                    <span
                        v-if="xpGain"
                        :key="burstKey"
                        class="xp-float absolute -top-1 right-0 text-sm font-extrabold text-purple-600 dark:text-purple-300"
                    >
                        +{{ xpGain }}
                    </span>
                </span>
            </div>

            <div class="px-5 pb-5 pt-4 transition-opacity duration-300 sm:px-6" :class="fading ? 'opacity-0' : 'opacity-100'">
                <div class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-400">
                    <span class="rounded-md bg-blue-50 px-2 py-0.5 text-blue-600 dark:bg-blue-950/60 dark:text-blue-300">{{ article.ref }}</span>
                    {{ article.law }}
                </div>

                <!-- Texto do artigo com lacunas -->
                <p class="min-h-[8.5rem] text-[17px] font-medium leading-[2.35rem] text-gray-800 dark:text-gray-100 sm:text-lg sm:leading-[2.5rem]">
                    <template v-for="(seg, i) in article.segments" :key="`${articleIndex}-${i}`">
                        <template v-if="typeof seg === 'string'">{{ seg }}</template>
                        <span
                            v-else
                            :ref="setGap(seg.gap)"
                            class="mx-0.5 inline-flex min-w-[3.25rem] items-center justify-center rounded-lg px-2 align-baseline font-bold leading-8 transition-colors duration-300"
                            :class="
                                correct[seg.gap]
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
                                    : filled[seg.gap]
                                      ? 'bg-yellow-100 text-gray-900 dark:bg-yellow-900/40 dark:text-yellow-100'
                                      : 'bg-yellow-100 text-yellow-700/80 dark:bg-yellow-900/30 dark:text-yellow-300/70'
                            "
                        >
                            <span v-if="filled[seg.gap]" class="gap-pop inline-block">{{ article.answers[seg.gap] }}</span>
                            <span v-else>(...)</span>
                        </span>
                    </template>
                </p>

                <!-- Banco de palavras -->
                <div class="mt-4 flex min-h-[5.75rem] flex-wrap content-start gap-2">
                    <span
                        v-for="word in article.bank"
                        :key="`${articleIndex}-${word}`"
                        :ref="setChip(word)"
                        class="rounded-xl border-2 px-3 py-1.5 text-sm font-bold transition-all duration-150"
                        :class="
                            used.has(word)
                                ? 'border-gray-200 bg-gray-100 text-transparent shadow-none dark:border-gray-700 dark:bg-gray-700/40'
                                : pressedChip === word
                                  ? 'translate-y-[3px] border-blue-300 bg-blue-50 text-blue-700 shadow-[0_1px_0_#93c5fd] dark:border-blue-700 dark:bg-blue-950 dark:text-blue-200'
                                  : 'border-gray-300 bg-white text-gray-800 shadow-[0_4px_0_#d4d4d4] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:shadow-[0_4px_0_#171717]'
                        "
                    >
                        {{ word }}
                    </span>
                </div>

                <div class="mt-4 flex justify-end border-t-2 border-gray-100 pt-4 dark:border-gray-700">
                    <span
                        class="relative rounded-xl border-2 px-5 py-2 text-sm font-extrabold uppercase tracking-wide transition-all duration-150"
                        :class="[
                            allFilled
                                ? 'border-green-600 bg-green-500 text-white shadow-[0_5px_0_#15803d]'
                                : 'border-gray-300 bg-gray-200 text-gray-400 shadow-[0_5px_0_#d4d4d4] dark:border-gray-600 dark:bg-gray-700 dark:shadow-[0_5px_0_#0f0f0f]',
                            verifyPressed && 'translate-y-[4px] !shadow-[0_1px_0_#15803d]',
                        ]"
                    >
                        Verificar
                        <span v-if="burstKey" :key="burstKey" class="pointer-events-none absolute left-1/2 top-1/2">
                            <i
                                v-for="(c, i) in confetti"
                                :key="i"
                                class="confetti absolute block h-2 w-2"
                                :class="c.round ? 'rounded-full' : 'rounded-[2px]'"
                                :style="{ background: c.color, '--dx': c.dx, '--dy': c.dy, '--rot': c.rot }"
                            />
                        </span>
                    </span>
                </div>
            </div>

            <!-- Faixa de acerto, igual à do jogo -->
            <div
                class="success-banner absolute inset-x-0 bottom-0 z-20 flex items-center gap-3 border-t-2 border-green-200 bg-green-50 px-5 py-4 dark:border-green-900 dark:bg-green-950"
                :class="success && !fading ? 'translate-y-0' : 'translate-y-full'"
            >
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-green-500 text-white">
                    <Check class="h-5 w-5" stroke-width="3" />
                </span>
                <div>
                    <p class="font-extrabold text-green-700 dark:text-green-300">Mandou bem!</p>
                    <p class="text-sm font-medium text-green-600 dark:text-green-400">100% de acerto · +25 XP</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.demo-progress {
    transition: width 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.gap-pop {
    animation: gap-pop 0.42s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes gap-pop {
    0% {
        transform: scale(0.6);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.success-banner {
    transition: transform 0.45s cubic-bezier(0.34, 1.4, 0.64, 1);
}

.xp-float {
    animation: xp-float 1.3s ease-out forwards;
}

@keyframes xp-float {
    0% {
        transform: translateY(6px) scale(0.6);
        opacity: 0;
    }
    20% {
        transform: translateY(-14px) scale(1.15);
        opacity: 1;
    }
    100% {
        transform: translateY(-38px) scale(1);
        opacity: 0;
    }
}

.streak-pop {
    animation: streak-pop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes streak-pop {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(1.7) rotate(-12deg);
    }
    100% {
        transform: scale(1);
    }
}

.confetti {
    animation: confetti 0.9s cubic-bezier(0.15, 0.7, 0.3, 1) forwards;
}

@keyframes confetti {
    0% {
        transform: translate(-50%, -50%) rotate(0deg) scale(1);
        opacity: 1;
    }
    100% {
        transform: translate(calc(-50% + var(--dx)), calc(-50% + var(--dy) + 40px)) rotate(var(--rot)) scale(0.6);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .gap-pop,
    .xp-float,
    .streak-pop,
    .confetti {
        animation: none;
    }
}
</style>
