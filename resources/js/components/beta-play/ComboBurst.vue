<script setup lang="ts">
import type { EnergyCombo } from '@/types/beta-play';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * A festa do combo (acertos seguidos na fase e no desafio): a tela pisca, raios cruzam a tela,
 * "COMBO x3" salta no meio com a energia ganha e um raiozinho voa até a bateria
 * ([data-energy-meter]); quando ele chega, `arrive` (a página credita a energia e o medidor
 * pulsa). Cresce com a sequência: x3 amarelo, x6 laranja ("Pegando fogo!"), x9 laranja forte
 * ("Imparável!"). Igual ao app (src/components/trail/ComboBurst.tsx). Com "reduzir movimento",
 * só credita.
 */
const props = defineProps<{ combo: EnergyCombo | null }>();
const emit = defineEmits<{ arrive: [] }>();

const BOLT = '#f7d84a';
const TIERS = [
    { c: '#efc12f', e: '#b38c17', bolts: 2, sparks: 0, kick: null },
    { c: '#f59e0b', e: '#b45309', bolts: 3, sparks: 10, kick: 'Pegando fogo!' },
    { c: '#f97316', e: '#c2410c', bolts: 5, sparks: 16, kick: 'Imparável!' },
] as const;
type Tier = (typeof TIERS)[number];

const layer = ref<HTMLElement | null>(null);
const shown = ref<{ combo: EnergyCombo; tier: Tier } | null>(null);
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

// A bateria visível na tela (a do topo da fase), para onde o raiozinho voa
function meterCenter(w: number) {
    const meters = Array.from(document.querySelectorAll<HTMLElement>('[data-energy-meter]'));
    const visible = meters.map((m) => m.getBoundingClientRect()).find((r) => r.width > 0 && r.bottom > 0 && r.top < window.innerHeight);
    return visible ? { x: visible.left + 12, y: visible.top + visible.height / 2 } : { x: w - 40, y: 32 };
}

async function run(combo: EnergyCombo) {
    const my = ++token;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) {
        emit('arrive');
        return;
    }

    const tier = TIERS[combo.streak >= 9 ? 2 : combo.streak >= 6 ? 1 : 0];
    shown.value = { combo, tier };
    await nextTick();
    const root = layer.value;
    if (!root || my !== token) return;

    const w = window.innerWidth;
    const h = window.innerHeight;
    const cy = h * 0.42;

    const flash = root.querySelector('.flash')!;
    play(flash, [{ opacity: 0 }, { opacity: 0.9, offset: 0.25 }, { opacity: 0 }], { duration: 520, easing: 'ease-out' });

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
            if (cls === 'bolt-edge') path.style.stroke = tier.e;
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
        spark.style.background = i % 3 === 0 ? '#2b8a52' : i % 2 ? tier.c : BOLT;
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
    await wait(550);
    if (my !== token) return;

    play(
        center,
        [
            { opacity: 1, transform: 'scale(1)' },
            { opacity: 0, transform: 'translateY(-20px) scale(.92)' },
        ],
        { duration: 280, easing: 'ease-in' },
    );
    const flyer = root.querySelector('.flyer')!;
    const from = { x: w / 2, y: cy + 40 };
    const to = meterCenter(w);
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
    emit('arrive');
    shown.value = null;
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
        <div v-if="shown" ref="layer" class="combo-burst" aria-hidden="true">
            <div class="flash" />
            <svg class="bolts" preserveAspectRatio="none" />
            <div class="sparks" />
            <div class="center">
                <div class="center-inner">
                    <p v-if="shown.tier.kick" class="kick" :style="{ color: shown.tier.e }">{{ shown.tier.kick }}</p>
                    <p class="word" :class="{ big: shown.combo.streak >= 9 }" :style="{ '--c': shown.tier.c, '--e': shown.tier.e }">
                        COMBO x{{ shown.combo.streak }}
                    </p>
                    <p v-if="shown.combo.gained > 0" class="gain">
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
                <svg viewBox="0 0 24 24" fill="#efc12f" stroke="#b38c17" stroke-width="2" stroke-linejoin="round">
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
.flash {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 42%, #fff4c2, transparent 70%);
    opacity: 0;
}
:global(.dark) .flash {
    background: radial-gradient(circle at 50% 42%, #4a4019, transparent 70%);
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
    stroke: #f7d84a;
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
.flyer svg {
    width: 100%;
    height: 100%;
}
</style>
