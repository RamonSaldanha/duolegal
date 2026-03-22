<script setup lang="ts">
import Icon from '@/components/Icon.vue';

interface Props {
    icon: string;
    color: string;
    level?: number;
    locked?: boolean;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    level: 1,
    locked: false,
    size: 'md',
});

const hexToRgb = (hex: string) => {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    if (!result) return { r: 0, g: 0, b: 0 };
    return {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16),
    };
};

const lighten = (hex: string, amount: number) => {
    const { r, g, b } = hexToRgb(hex);
    return `rgb(${Math.round(Math.min(255, r + (255 - r) * amount))}, ${Math.round(Math.min(255, g + (255 - g) * amount))}, ${Math.round(Math.min(255, b + (255 - b) * amount))})`;
};

const darken = (hex: string, amount: number) => {
    const { r, g, b } = hexToRgb(hex);
    return `rgb(${Math.round(r * (1 - amount))}, ${Math.round(g * (1 - amount))}, ${Math.round(b * (1 - amount))})`;
};

const sizeMap = {
    sm: { wrapper: 'w-16 h-16', icon: 28, shield: 'w-6 h-7', shieldText: 'text-[9px]', shieldBottom: '-6px' },
    md: { wrapper: 'w-28 h-28', icon: 52, shield: 'w-9 h-10', shieldText: 'text-[11px]', shieldBottom: '-8px' },
    lg: { wrapper: 'w-36 h-36', icon: 68, shield: 'w-11 h-12', shieldText: 'text-sm', shieldBottom: '-10px' },
};

const s = sizeMap[props.size];

const grey = {
    outer: '#9CA3AF',
    band: '#E5E7EB',
    inner: '#D1D5DB',
    fill: '#F3F4F6',
    shieldBorder: '#D1D5DB',
    shieldFill: '#E5E7EB',
};
</script>

<template>
    <div class="relative inline-flex items-center justify-center" :class="s.wrapper">
        <!-- SVG Badge -->
        <svg class="w-full h-full" viewBox="0 0 112 112">
            <template v-if="!locked">
                <!-- Outer thick ring -->
                <circle cx="56" cy="56" r="53" fill="none" :stroke="color" stroke-width="5" />
                <!-- Middle light band -->
                <circle cx="56" cy="56" r="48" :fill="lighten(color, 0.82)" />
                <!-- Inner ring -->
                <circle cx="56" cy="56" r="42" fill="none" :stroke="color" stroke-width="2" />
                <!-- Inner circle fill -->
                <circle cx="56" cy="56" r="40" :fill="lighten(color, 0.93)" />
            </template>
            <template v-else>
                <circle cx="56" cy="56" r="53" fill="none" :stroke="grey.outer" stroke-width="5" />
                <circle cx="56" cy="56" r="48" :fill="grey.band" />
                <circle cx="56" cy="56" r="42" fill="none" :stroke="grey.inner" stroke-width="2" />
                <circle cx="56" cy="56" r="40" :fill="grey.fill" />
            </template>
        </svg>

        <!-- Icon -->
        <div class="absolute inset-0 flex items-center justify-center">
            <Icon
                v-if="!locked"
                :name="icon"
                :size="s.icon"
                color="#1F2937"
                :stroke-width="1.5"
                class="!h-auto !w-auto dark:!text-gray-200"
            />
            <Icon
                v-else
                name="Lock"
                :size="s.icon"
                color="#9CA3AF"
                :stroke-width="1.5"
                class="!h-auto !w-auto"
            />
        </div>

        <!-- Shield -->
        <div class="absolute left-1/2 -translate-x-1/2" :class="[s.shield]" :style="{ bottom: s.shieldBottom }">
            <svg class="w-full h-full" viewBox="0 0 36 40" fill="none">
                <template v-if="!locked">
                    <!-- Shield outer border -->
                    <path
                        d="M18 1 L34 7 L34 18 Q34 32 18 39 Q2 32 2 18 L2 7 Z"
                        :fill="lighten(color, 0.4)"
                        :stroke="color"
                        stroke-width="1.5"
                    />
                    <!-- Shield inner fill -->
                    <path
                        d="M18 4.5 L31 9.5 L31 18 Q31 30 18 36 Q5 30 5 18 L5 9.5 Z"
                        :fill="lighten(color, 0.7)"
                        :stroke="color"
                        stroke-width="0.8"
                    />
                </template>
                <template v-else>
                    <path
                        d="M18 1 L34 7 L34 18 Q34 32 18 39 Q2 32 2 18 L2 7 Z"
                        :fill="grey.shieldBorder"
                        stroke="#9CA3AF"
                        stroke-width="1.5"
                    />
                    <path
                        d="M18 4.5 L31 9.5 L31 18 Q31 30 18 36 Q5 30 5 18 L5 9.5 Z"
                        :fill="grey.shieldFill"
                        stroke="#9CA3AF"
                        stroke-width="0.8"
                    />
                </template>
            </svg>
            <span
                class="absolute inset-0 flex items-center justify-center text-gray-900 font-black"
                :class="s.shieldText"
                style="padding-bottom: 1px;"
            >{{ level }}</span>
        </div>
    </div>
</template>
