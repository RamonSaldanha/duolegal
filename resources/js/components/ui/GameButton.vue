<template>
    <Link v-if="href" :href="href" :class="classes" v-bind="otherAttrs">
        <slot />
    </Link>
    <button v-else :class="classes" :disabled="disabled" v-bind="otherAttrs">
        <slot />
    </button>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, useAttrs } from 'vue';

// Botão "3D" do jogo: a borda de baixo (sombra) encolhe enquanto a face desce, então o
// botão afunda no lugar em vez de só se mover. Tudo em CSS (:hover e :active), para o
// efeito nunca ficar dessincronizado quando o Vue reaproveita o botão ou ele é desabilitado.
interface Props {
    variant?: 'white' | 'green' | 'purple' | 'red' | 'blue';
    size?: 'sm' | 'md' | 'lg';
    disabled?: boolean;
    href?: string;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'white',
    size: 'md',
    disabled: false,
    href: undefined,
});

const attrs = useAttrs();

// O href vai para o Link, não para os atributos repassados
const otherAttrs = computed(() => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { href: _, ...rest } = attrs as Record<string, unknown>;
    return rest;
});

const variants = {
    white: 'game-button-white bg-white text-gray-900 border-gray-300 dark:bg-gray-700 dark:text-white dark:border-gray-600',
    green: 'game-button-green bg-green-500 hover:bg-green-600 text-white border-green-700',
    purple: 'game-button-purple bg-purple-500 hover:bg-purple-600 disabled:bg-gray-400 text-white border-purple-700 disabled:border-gray-600',
    red: 'game-button-red bg-red-500 hover:bg-red-600 text-white border-red-700',
    blue: 'game-button-blue bg-blue-500 hover:bg-blue-600 text-white border-blue-700',
};

const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2',
    lg: 'px-5 py-2.5 text-base',
};

const classes = computed(() => [
    'game-button inline-flex items-center justify-center rounded-lg border-4 font-bold disabled:cursor-not-allowed disabled:opacity-50',
    variants[props.variant],
    sizes[props.size],
]);
</script>

<style scoped>
.game-button {
    --edge: theme('colors.gray.300');
    box-shadow: 0 4px 0 var(--edge);
    transition:
        transform 0.1s ease,
        box-shadow 0.1s ease,
        background-color 0.15s ease;
}

/* A borda de baixo usa a mesma cor da borda de cada variante (as classes border-* acima) */
.game-button-white {
    --edge: theme('colors.gray.300');
}
.dark .game-button-white {
    --edge: theme('colors.gray.600');
}
.game-button-green {
    --edge: theme('colors.green.700');
}
.game-button-purple {
    --edge: theme('colors.purple.700');
}
.game-button-purple:disabled {
    --edge: theme('colors.gray.600');
}
.game-button-red {
    --edge: theme('colors.red.700');
}
.game-button-blue {
    --edge: theme('colors.blue.700');
}

/* Mouse por cima: desce 2px e a borda encolhe 2px. Só em quem tem mouse, para o toque
   no celular não deixar o botão meio afundado depois de soltar. */
@media (hover: hover) {
    .game-button:not(:disabled):hover {
        transform: translateY(2px);
        box-shadow: 0 2px 0 var(--edge);
    }
}

/* Apertado: desce os 4px inteiros e a borda some */
.game-button:not(:disabled):active {
    transform: translateY(4px);
    box-shadow: 0 0 0 var(--edge);
}
</style>
