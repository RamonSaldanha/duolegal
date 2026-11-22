<script setup lang="ts">
import type { EnergyCombo } from '@/types/beta-play';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * A festa do combo (acertos seguidos na fase e no desafio): a tela pisca, raios cruzam a tela,
 * "COMBO x3" salta no meio com o ganho e termina num destino; aí `arrive`. Cresce com a
 * sequência (x3, x6 "Pegando fogo!", x9 "Imparável!").
 *
 * - Quem não assina ganha energia: tudo amarelo e laranja, e um raiozinho voa até a bateria
 *   ([data-energy-meter]); a página credita a energia quando ele chega e o medidor pulsa.
 * - Quem assina ganha XP (`combo.xp`): a tempestade é azul e o "+5 XP" se parte em faíscas que
 *   descem até o "+5 XP" da barra de resultado ([data-combo-xp]), que acende a cada uma
 *   (docs/design/plus-barra-combo.html, A3). Não há contador de XP na fase.
 *
 * Igual ao app (src/components/trail/ComboBurst.tsx). Com "reduzir movimento", só credita.
 */
const props = defineProps<{ combo: EnergyCombo | null }>();
const emit = defineEmits<{ arrive: [] }>();

type Tier = { c: string; e: string; bolt: string; bolts: number; sparks: number; kick: string | null };
const TIERS: Tier[] = [
    { c: '#efc12f', e: '#b38c17', bolt: '#f7d84a', bolts: 2, sparks: 0, kick: null },
    { c: '#f59e0b', e: '#b45309', bolt: '#f7d84a', bolts: 3, sparks: 10, kick: 'Pegando fogo!' },
    { c: '#f97316', e: '#c2410c', bolt: '#f7d84a', bolts: 5, sparks: 16, kick: 'Imparável!' },
];
// O combo de quem assina: azul, do mais claro ao mais forte
const XP_TIERS: Tier[] = [
    { c: '#7db3ff', e: '#1d4ed8', bolt: '#7db3ff', bolts: 3, sparks: 0, kick: null },
    { c: '#60a5fa', e: '#1e40af', bolt: '#7db3ff', bolts: 4, sparks: 10, kick: 'Eletrizante!' },
    { c: '#93c5fd', e: '#1e3a8a', bolt: '#a5c8ff', bolts: 6, sparks: 16, kick: 'Imparável!' },
];
// Em quantas faíscas o XP se parte
const XP_SPARKS = 5;

const layer = ref<HTMLElement | null>(null);
const shown = ref<{ combo: EnergyCombo; tier: Tier; xp: boolean } | null>(null);
let token = 0;

const play = (el: Element, keyframes: Keyframe[], options: KeyframeAnimationOptions = {}) =>
    el
        .animate(keyframes, { fill: 'forwards', easing: 'cubic-bezier(.22,1,.36,1)', ...options })
        .finished.then(() => undefined)
        .catch(() => undefined);
const wait = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

function zigzag(w: number, h: number, i: number, n: number) {
    const x0 = w * (0.12 + 0.76 * ((i + 0.5) / n)) + (Math.random() - 0.5) * 30;
    const drift = (i % 2 ? -1 : 1) * w * 0.3;
    let d = `M ${x0.toFixed(1)} -30`;
    for (let k = 1; k <= 6; k++) {
        const y = -30 + ((h + 60) * k) / 6;
        const x = x0 + (drift * k) / 6 + (k % 2 ? 1 : -1) * (20 + Math.random() * 24);
        d += ` L ${x.toFixed(1)} ${y.toFixed(1)}`;
    }
    return d;
}

// O elemento visível na tela para onde o raiozinho (ou as faíscas) vai
function visible(selector: string) {
    return Array.from(document.querySelectorAll<HTMLElement>(selector)).find((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.bottom > 0 && r.top < window.innerHeight;
    });
}
function centerOf(el: HTMLElement | undefined, fallback: { x: number; y: number }) {
    if (!el) return fallback;
    const r = el.getBoundingClientRect();
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
}

async function run(combo: EnergyCombo) {
    const my = ++token;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const xp = (combo.xp ?? 0) > 0;
    if (reduce) {
        emit('arrive');
        return;
    }

    const tier = (xp ? XP_TIERS : TIERS)[combo.streak >= 9 ? 2 : combo.streak >= 6 ? 1 : 0];
    shown.value = { combo, tier, xp };
    await nextTick();
    const root = layer.value;
    if (!root || my !== token) return;

    const w = window.innerWidth;
    const h = window.innerHeight;
    const cy = h * 0.42;

    const flash = root.querySelector('.flash')!;
    play(flash, [{ opacity: 0 }, { opacity: xp ? 0.3 : 0.6, offset: 0.25 }, { opacity: 0 }], { duration: 520, easing: 'ease-out' });

    const svg = root.querySelector('svg.bolts')!;
    svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    svg.replaceChildren();
    for (let i = 0; i < tier.bolts; i++) {
        const d = zigzag(w, h, i, tier.bolts);
        for (const cls of ['bolt-edge', 'bolt']) {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            path.setAttribute('pathLength', '1');
            path.setAttribute('class', cls);
            path.style.stroke = cls === 'bolt-edge' ? tier.e : tier.bolt;
            svg.append(path);
            const peak = cls === 'bolt' ? 0.95 : 0.3;
            play(
                path,
                [
                    { strokeDashoffset: 1, opacity: peak },
                    { strokeDashoffset: 0, opacity: peak, offset: 0.3 },
                    { strokeDashoffset: 0, opacity: 0 },
                ],
                { duration: 950, delay: i * 70, easing: 'ease-out' },
            );
        }
    }

    const sparks = root.querySelector('.sparks')!;
    sparks.replaceChildren();
    for (let i = 0; i < tier.sparks; i++) {
        const spark = document.createElement('i');
        spark.style.background = i % 3 === 0 ? (xp ? '#3b82f6' : '#2b8a52') : i % 2 ? tier.c : tier.bolt;
        sparks.append(spark);
        const a = (Math.PI * 2 * i) / tier.sparks + Math.random() * 0.5;
        const dist = 120 * (0.6 + Math.random() * 0.6);
        play(
            spark,
            [
                { transform: `translate(${w / 2}px, ${cy}px) rotate(0) scale(1)`, opacity: 1 },
                {
                    transform: `translate(${w / 2 + Math.cos(a) * dist}px, ${cy + Math.sin(a) * dist}px) rotate(${180 + Math.random() * 180}deg) scale(.4)`,
                    opacity: 0,
                },
            ],
            { duration: 700 + Math.random() * 300, easing: 'cubic-bezier(.2,.8,.3,1)' },
        );
    }

    const center = root.querySelector('.center-inner')!;
    await play(
        center,
        [
            { transform: 'scale(.3) rotate(-10deg)', opacity: 0 },
            { transform: 'scale(1.18) rotate(3deg)', opacity: 1, offset: 0.6 },
            { transform: 'scale(1) rotate(0)', opacity: 1 },
        ],
        { duration: 440 },
    );
    if (my !== token) return;
    await wait(xp ? 650 : 550);
    if (my !== token) return;

    play(
        center,
        [
            { opacity: 1, transform: 'scale(1)' },
            { opacity: 0, transform: 'translateY(-20px) scale(.92)' },
        ],
        { duration: 280, easing: 'ease-in' },
    );
    const from = { x: w / 2, y: cy + 40 };

    if (xp) {
        await sparksToResult(root, from, my);
        if (my !== token) return;
    } else {
        const flyer = root.querySelector('.flyer')!;
        const to = centerOf(visible('[data-energy-meter]'), { x: w - 40, y: 32 });
        const mid = { x: (from.x + to.x) / 2 + 30, y: Math.min(from.y, to.y) + (from.y - to.y) * 0.25 };
        await play(
            flyer,
            [
                { transform: `translate(${from.x - 15}px, ${from.y - 15}px) scale(1.5)`, opacity: 1 },
                { transform: `translate(${mid.x - 15}px, ${mid.y - 15}px) scale(1.1)`, opacity: 1, offset: 0.5 },
                { transform: `translate(${to.x - 15}px, ${to.y - 15}px) scale(.5)`, opacity: 0.2 },
            ],
            { duration: 620, easing: 'cubic-bezier(.5,0,.3,1)' },
        );
        if (my !== token) return;
    }
    emit('arrive');
    shown.value = null;
}

// O "+5 XP" se parte em faíscas que descem uma a uma até o "+5 XP" da barra de resultado, que
// acende a cada uma que chega
async function sparksToResult(root: HTMLElement, from: { x: number; y: number }, my: number) {
    const target = visible('[data-combo-xp]');
    const to = centerOf(target, { x: from.x, y: window.innerHeight - 80 });
    const icon = root.querySelector('.flyer')!.innerHTML;
    const flights = Array.from({ length: XP_SPARKS }, (_, i) => {
        const el = document.createElement('span');
        el.className = 'xp-spark';
        el.innerHTML = icon;
        root.append(el);
        const spread = (i - (XP_SPARKS - 1) / 2) * 28;
        const midY = from.y + (to.y - from.y) * 0.45;
        return play(
            el,
            [
                { transform: `translate(${from.x - 10 + spread / 3}px, ${from.y - 10}px) scale(1.3)`, opacity: 1 },
                { transform: `translate(${(from.x + to.x) / 2 + spread - 10}px, ${midY - 10}px) scale(1)`, opacity: 1, offset: 0.5 },
                { transform: `translate(${to.x - 10}px, ${to.y - 10}px) scale(.5)`, opacity: 0.2 },
            ],
            { duration: 520, delay: i * 90, easing: 'cubic-bezier(.5,0,.3,1)' },
        ).then(() => {
            el.remove();
            if (my !== token || !target) return;
            target.animate([{ scale: '1' }, { scale: '1.25', offset: 0.4 }, { scale: '1' }], { duration: 220, easing: 'ease-out' });
            target.classList.add('is-lit');
        });
    });
    await Promise.all(flights);
    await wait(250);
}

watch(
    () => props.combo,
    (combo) => {
        if (combo) run(combo);
    },
);

onBeforeUnmount(() => {
    token++;
});
</script>

<template>
    <Teleport to="body">
        <div v-if="shown" ref="layer" class="combo-burst" :class="{ 'is-xp': shown.xp }" aria-hidden="true">
            <div class="flash" />
            <svg class="bolts" preserveAspectRatio="none" />
            <div class="sparks" />
            <div class="center">
                <div class="center-inner">
                    <p v-if="shown.tier.kick" class="kick" :style="{ color: shown.tier.e }">{{ shown.tier.kick }}</p>
                    <p class="word" :class="{ big: shown.combo.streak >= 9 }" :style="{ '--c': shown.tier.c, '--e': shown.tier.e }">
                        COMBO x{{ shown.combo.streak }}
                    </p>
                    <p v-if="shown.xp" class="gain gain-xp">
                        <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                            <path
                                d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"
                            />
                        </svg>
                        +{{ shown.combo.xp }} XP
                    </p>
                    <p v-else-if="shown.combo.gained > 0" class="gain">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m11 7-3 5h4l-3 5" />
                            <path d="M14.856 6H16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.935" />
                            <path d="M5.14 18H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h2.936" />
                            <path d="M22 14v-4" />
                        </svg>
                        +{{ shown.combo.gained }} de energia
                    </p>
                </div>
            </div>
            <div class="flyer">
                <svg
                    viewBox="0 0 24 24"
                    :fill="shown.xp ? '#7db3ff' : '#efc12f'"
                    :stroke="shown.xp ? '#1d4ed8' : '#b38c17'"
                    stroke-width="2"
                    stroke-linejoin="round"
                >
                    <path
                        d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"
                    />
                </svg>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.combo-burst {
    position: fixed;
    inset: 0;
    z-index: 60;
    pointer-events: none;
    overflow: hidden;
}
/* Lampejo chapado: amarelo-claro para a energia, azul para o XP */
.flash {
    position: absolute;
    inset: 0;
    background: #fff4c2;
    opacity: 0;
}
:global(.dark) .flash {
    background: #4a4019;
}
.is-xp .flash,
:global(.dark) .is-xp .flash {
    background: var(--mz-xp);
}
.bolts {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}
.bolts :deep(path) {
    fill: none;
    stroke-linejoin: miter;
    stroke-linecap: square;
    stroke-dasharray: 1;
    stroke-dashoffset: 1;
}
.bolts :deep(.bolt) {
    stroke-width: 14;
}
.bolts :deep(.bolt-edge) {
    stroke-width: 22;
}
.sparks :deep(i) {
    position: absolute;
    left: -4px;
    top: -4px;
    width: 9px;
    height: 9px;
    border-radius: 2px;
}
.center {
    position: absolute;
    left: 50%;
    top: 42%;
    transform: translate(-50%, -50%);
}
.center-inner {
    display: grid;
    justify-items: center;
    gap: 10px;
    opacity: 0;
}
.kick {
    font-size: 18px;
    font-weight: 900;
}
/* Contorno só por fora da letra (o traço vai atrás do preenchimento), para não tampar os buracos
   do O, do B e do x; a base 3D é uma sombra reta, como nos botões */
.word {
    font-size: 44px;
    font-weight: 900;
    line-height: 1.1;
    white-space: nowrap;
    letter-spacing: 0.04em;
    color: var(--c);
    -webkit-text-stroke: 0.09em var(--e);
    paint-order: stroke fill;
    text-shadow: 0 0.08em 0 var(--e);
}
.word.big {
    font-size: 50px;
}
.gain {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px 4px 8px;
    border-radius: 999px;
    background: #f6ecc8;
    color: #5e4800;
    font-size: 15px;
    font-weight: 900;
}
:global(.dark) .gain {
    background: #3a3214;
    color: #f1dc95;
}
.gain-xp,
:global(.dark) .gain-xp {
    background: var(--mz-xp);
    color: #fff;
}
.gain svg {
    width: 18px;
    height: 18px;
}
.flyer {
    position: absolute;
    left: 0;
    top: 0;
    width: 30px;
    height: 30px;
    opacity: 0;
}
.flyer svg,
.combo-burst :deep(.xp-spark svg) {
    width: 100%;
    height: 100%;
}
/* As faíscas do XP são criadas no script, então o estilo precisa do :deep */
.combo-burst :deep(.xp-spark) {
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
}
</style>
