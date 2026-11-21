<template>
    <div class="segmented" role="group" :aria-label="label">
        <button
            v-for="option in options"
            :key="String(option.value)"
            type="button"
            :aria-pressed="option.value === modelValue"
            @click="emit('update:modelValue', option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>

<script setup lang="ts" generic="T extends string | number">
// Alternador (ranking, ofensiva): trilho rebaixado, e a opção ativa é uma peça clara com base de
// 2 px, como uma tecla levantada.
defineProps<{
    modelValue: T;
    options: { value: T; label: string }[];
    label?: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: T] }>();
</script>

<style scoped>
.segmented {
    display: inline-flex;
    gap: 4px;
    padding: 4px;
    border-radius: 14px;
    background: hsl(var(--secondary));
    box-shadow: inset 0 2px 0 var(--mz-press);
}
.segmented button {
    border: 0;
    background: none;
    border-radius: 10px;
    min-height: 34px;
    padding: 0 14px;
    font-size: 14px;
    font-weight: 700;
    color: hsl(var(--muted-foreground));
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition:
        background-color 0.12s,
        box-shadow 0.12s,
        color 0.12s;
}
.segmented button[aria-pressed='true'] {
    background: hsl(var(--card));
    color: hsl(var(--foreground));
    box-shadow:
        inset 0 0 0 2px hsl(var(--border)),
        0 2px 0 var(--mz-edge);
}
@media (hover: hover) {
    .segmented button[aria-pressed='false']:hover {
        color: hsl(var(--foreground));
    }
}
.segmented button:focus-visible {
    outline: 3px solid hsl(var(--ring));
    outline-offset: 2px;
}
</style>
