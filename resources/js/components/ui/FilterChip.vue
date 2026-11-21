<template>
    <button type="button" class="filter-chip" :aria-pressed="modelValue" @click="emit('update:modelValue', !modelValue)">
        <Check v-if="modelValue" class="h-3.5 w-3.5" :stroke-width="3" aria-hidden="true" />
        <slot />
    </button>
</template>

<script setup lang="ts">
import { Check } from 'lucide-vue-next';

// Chip de filtro: escolher é azul, como o cartão de escolha, e o escolhido ganha o check.
// Calmo: não afunda, aperta para dentro.
defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<style scoped>
.filter-chip {
    --c: hsl(var(--border));
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 36px;
    border: 0;
    border-radius: 9999px;
    padding: 0 14px;
    background: hsl(var(--card));
    color: var(--mz-ink2);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: inset 0 0 0 2px var(--c);
    -webkit-tap-highlight-color: transparent;
    transition:
        background-color 0.12s,
        box-shadow 0.12s;
}
.filter-chip[aria-pressed='true'] {
    --c: var(--mz-choice);
    background: var(--mz-choice-soft);
    color: var(--mz-choice-ink);
}
@media (hover: hover) {
    .filter-chip[aria-pressed='false']:hover {
        background: hsl(var(--secondary));
    }
}
.filter-chip:active {
    box-shadow:
        inset 0 0 0 2px var(--c),
        inset 0 3px 0 var(--mz-press);
}
.filter-chip:focus-visible {
    outline: 3px solid hsl(var(--ring));
    outline-offset: 2px;
}
</style>
