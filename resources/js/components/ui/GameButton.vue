<template>
    <Link v-if="href" :href="href" :class="classes" v-bind="otherAttrs">
        <slot />
    </Link>
    <button v-else :class="classes" :disabled="disabled || loading" :aria-busy="loading || undefined" v-bind="otherAttrs">
        <span v-if="loading" class="mz-spin" aria-hidden="true" />
        <slot />
    </button>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, useAttrs } from 'vue';

// Botão da família do site (docs/design/componentes.html). As classes .mz-* ficam em
// resources/css/app.css e são as mesmas do Button do shadcn.
// - De ação, afundam ao apertar: main (amarelo, um por tela), second (neutro), danger,
//   success (só acerto) e premium (só Premium e Dia da prova).
// - Calmos, não afundam: tonal, tonal-brand, tonal-danger, outline, dashed, ghost.
// Os nomes antigos continuam valendo: green, purple e blue viram main, white vira second e
// red vira danger.
type FamilyVariant = 'main' | 'second' | 'danger' | 'success' | 'premium' | 'tonal' | 'tonal-brand' | 'tonal-danger' | 'outline' | 'dashed' | 'ghost';
type LegacyVariant = 'white' | 'green' | 'purple' | 'red' | 'blue';

interface Props {
    variant?: FamilyVariant | LegacyVariant;
    size?: 'xs' | 'sm' | 'md' | 'lg';
    disabled?: boolean;
    loading?: boolean;
    /** Só ícone: quadrado com a altura do tamanho */
    square?: boolean;
    href?: string;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'second',
    size: 'md',
    disabled: false,
    loading: false,
    square: false,
    href: undefined,
});

const attrs = useAttrs();

// O href vai para o Link, não para os atributos repassados
const otherAttrs = computed(() => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { href: _, ...rest } = attrs as Record<string, unknown>;
    return rest;
});

const LEGACY: Record<LegacyVariant, FamilyVariant> = {
    white: 'second',
    green: 'main',
    purple: 'main',
    blue: 'main',
    red: 'danger',
};

const variant = computed<FamilyVariant>(() => LEGACY[props.variant as LegacyVariant] ?? (props.variant as FamilyVariant));

const classes = computed(() => ['mz-btn', `mz-${props.size}`, `mz-${variant.value}`, props.square && 'mz-sq']);
</script>
