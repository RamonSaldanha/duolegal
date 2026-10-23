<script setup lang="ts">
import { computed } from 'vue';

/**
 * A caixa de presente dos lembretes por e-mail. Cor chapada e a "sombra" sólida
 * embaixo de cada peça, como os botões do jogo.
 *
 * O PNG que vai no e-mail (public/img/email/gift-*.png) é este mesmo desenho
 * fechado, no tema claro. Se mudar o desenho aqui, gere os PNGs de novo.
 */
interface Props {
    variant?: 'lives' | 'xp';
    opened?: boolean;
}

const props = withDefaults(defineProps<Props>(), { variant: 'lives', opened: false });

const palette = computed(() =>
    props.variant === 'xp'
        ? { body: '#a855f7', bodyShadow: '#7e22ce', shade: '#9333ea', lid: '#c084fc', lidShadow: '#9333ea', shine: '#e9d5ff' }
        : { body: '#3b82f6', bodyShadow: '#1d4ed8', shade: '#2563eb', lid: '#60a5fa', lidShadow: '#2563eb', shine: '#bfdbfe' },
);

const heart =
    'M12 21s-7.5-4.6-9.6-9.2C.7 7.9 3.2 3.5 7.3 3.5c2.1 0 3.6 1.1 4.7 2.6 1.1-1.5 2.6-2.6 4.7-2.6 4.1 0 6.6 4.4 4.9 8.3C19.5 16.4 12 21 12 21z';
const bolt = 'M14 1 3 14h7l-2 9 11-13h-7z';
const sparkle = 'M0-9C1-3 3-1 9 0 3 1 1 3 0 9-1 3-3 1-9 0-3-1-1-3 0-9z';
</script>

<template>
    <svg
        viewBox="0 0 240 240"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="Caixa de presente"
        :class="['gift', { 'gift--open': opened }]"
    >
        <circle
            cx="120"
            cy="128"
            r="98"
            :class="variant === 'xp' ? 'fill-purple-50 dark:fill-purple-950/40' : 'fill-blue-50 dark:fill-blue-950/40'"
        />
        <ellipse cx="120" cy="216" rx="72" ry="8" class="fill-gray-200 dark:fill-gray-800" />

        <!-- O que sai da caixa -->
        <g class="gift__burst">
            <template v-if="variant === 'lives'">
                <g transform="translate(14 36) rotate(-14 18 18) scale(1.5)">
                    <path :d="heart" fill="#b91c1c" transform="translate(0 2.5)" />
                    <path :d="heart" fill="#ef4444" />
                    <ellipse cx="7.5" cy="8" rx="2.4" ry="1.6" fill="#fecaca" transform="rotate(-30 7.5 8)" />
                </g>
                <g transform="translate(176 22) rotate(12 22 22) scale(1.8)">
                    <path :d="heart" fill="#b91c1c" transform="translate(0 2.5)" />
                    <path :d="heart" fill="#ef4444" />
                    <ellipse cx="7.5" cy="8" rx="2.4" ry="1.6" fill="#fecaca" transform="rotate(-30 7.5 8)" />
                </g>
                <g transform="translate(196 122) rotate(16 12 12) scale(1.05)">
                    <path :d="heart" fill="#b91c1c" transform="translate(0 2.5)" />
                    <path :d="heart" fill="#ef4444" />
                </g>
                <g transform="translate(22 132) rotate(-10 12 12) scale(0.95)">
                    <path :d="heart" fill="#b91c1c" transform="translate(0 2.5)" />
                    <path :d="heart" fill="#ef4444" />
                </g>
            </template>
            <template v-else>
                <g transform="translate(20 34) rotate(-12 12 12) scale(1.7)">
                    <path :d="bolt" fill="#ca8a04" transform="translate(0 2)" />
                    <path :d="bolt" fill="#facc15" />
                </g>
                <g transform="translate(196 118) rotate(14 12 12) scale(1.15)">
                    <path :d="bolt" fill="#ca8a04" transform="translate(0 2)" />
                    <path :d="bolt" fill="#facc15" />
                </g>
                <g transform="translate(166 30) rotate(10 30 16)">
                    <rect x="0" y="5" width="60" height="32" rx="12" fill="#7e22ce" />
                    <rect x="0" y="0" width="60" height="32" rx="12" fill="#a855f7" />
                    <text
                        x="30"
                        y="23"
                        text-anchor="middle"
                        font-family="Poppins, 'Segoe UI', Arial, sans-serif"
                        font-size="19"
                        font-weight="900"
                        fill="#ffffff"
                    >
                        XP
                    </text>
                </g>
                <g transform="translate(16 128) rotate(-8 22 14)">
                    <rect x="0" y="4" width="46" height="26" rx="10" fill="#7e22ce" />
                    <rect x="0" y="0" width="46" height="26" rx="10" fill="#c084fc" />
                    <text
                        x="23"
                        y="18"
                        text-anchor="middle"
                        font-family="Poppins, 'Segoe UI', Arial, sans-serif"
                        font-size="13"
                        font-weight="900"
                        fill="#ffffff"
                    >
                        +50%
                    </text>
                </g>
            </template>
            <path :d="sparkle" fill="#facc15" transform="translate(70 40) scale(0.9)" />
            <path :d="sparkle" fill="#facc15" transform="translate(160 96) scale(0.7)" />
            <path :d="sparkle" fill="#facc15" transform="translate(44 100) scale(0.55)" />
        </g>

        <!-- Caixa -->
        <g class="gift__box">
            <rect x="58" y="126" width="124" height="86" rx="12" :fill="palette.bodyShadow" />
            <rect x="108" y="126" width="24" height="86" fill="#ca8a04" />
            <rect x="58" y="118" width="124" height="86" rx="12" :fill="palette.body" />
            <rect x="58" y="118" width="124" height="12" :fill="palette.shade" />
            <rect x="108" y="118" width="24" height="86" fill="#facc15" />
            <rect x="108" y="118" width="24" height="12" fill="#eab308" />
            <rect x="70" y="140" width="8" height="44" rx="4" :fill="palette.lid" />
        </g>

        <!-- Tampa e laço -->
        <g class="gift__lid">
            <rect x="48" y="98" width="144" height="32" rx="10" :fill="palette.lidShadow" />
            <rect x="106" y="98" width="28" height="32" fill="#eab308" />
            <rect x="48" y="92" width="144" height="32" rx="10" :fill="palette.lid" />
            <rect x="106" y="92" width="28" height="32" fill="#fde047" />
            <rect x="58" y="99" width="34" height="6" rx="3" :fill="palette.shine" />

            <path d="M120 92C104 60 72 62 78 82c3 10 22 12 42 10z" fill="#facc15" />
            <path d="M120 92c16-32 48-30 42-10-3 10-22 12-42 10z" fill="#facc15" />
            <ellipse cx="94" cy="81" rx="9" ry="5" fill="#ca8a04" transform="rotate(20 94 81)" />
            <ellipse cx="146" cy="81" rx="9" ry="5" fill="#ca8a04" transform="rotate(-20 146 81)" />
            <rect x="109" y="80" width="22" height="17" rx="7" fill="#eab308" />
            <rect x="113" y="83" width="8" height="4" rx="2" fill="#fde047" />
        </g>
    </svg>
</template>

<style scoped>
.gift__box,
.gift__lid,
.gift__burst {
    transform-box: view-box;
    transform-origin: 120px 200px;
}

/* Fechada: a caixa dá umas balançadas de vez em quando, pedindo para ser aberta. */
.gift:not(.gift--open) .gift__box,
.gift:not(.gift--open) .gift__lid {
    animation: gift-wiggle 2.6s ease-in-out infinite;
}

.gift:not(.gift--open) .gift__burst {
    animation: gift-float 3.2s ease-in-out infinite;
}

/* Aberta: a tampa voa e o que estava dentro salta. */
.gift--open .gift__lid {
    transform-origin: 120px 110px;
    animation: gift-lid-off 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.gift--open .gift__burst {
    transform-origin: 120px 140px;
    animation: gift-burst 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes gift-wiggle {
    0%,
    70%,
    100% {
        transform: rotate(0deg);
    }
    76% {
        transform: rotate(-5deg);
    }
    82% {
        transform: rotate(5deg);
    }
    88% {
        transform: rotate(-3deg);
    }
    94% {
        transform: rotate(2deg);
    }
}

@keyframes gift-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

@keyframes gift-lid-off {
    to {
        transform: translate(-34px, -58px) rotate(-24deg);
    }
}

@keyframes gift-burst {
    0% {
        transform: scale(0.6);
    }
    100% {
        transform: scale(1.12) translateY(-6px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .gift__box,
    .gift__lid,
    .gift__burst {
        animation: none !important;
    }

    .gift--open .gift__lid {
        transform: translate(-34px, -58px) rotate(-24deg);
    }
}
</style>
