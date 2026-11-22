<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

// O botão de assinar: azul do mapa (blue-500) com a base no blue-700, afunda ao apertar. É o
// único azul de ação das telas de Premium (o amarelo segue sendo o principal do resto).
defineProps<{ href?: string; disabled?: boolean; type?: 'button' | 'submit' }>();
defineEmits<{ click: [event: MouseEvent] }>();
</script>

<template>
    <Link v-if="href" :href="href" class="mz-btn mz-lg premium-btn w-full"><slot /></Link>
    <button v-else :type="type ?? 'button'" class="mz-btn mz-lg premium-btn w-full" :disabled="disabled" @click="$emit('click', $event)">
        <slot />
    </button>
</template>

<style scoped>
.premium-btn {
    --e: #1d4ed8;
    background: #3b82f6;
    color: #fff;
    box-shadow: 0 var(--d) 0 var(--e);

    @media (hover: hover) {
        &:hover:not(:disabled) {
            background: #2563eb;
        }
    }
    &:active:not(:disabled) {
        transform: translateY(var(--d));
        box-shadow: 0 0 0 var(--e);
    }
    &:disabled {
        opacity: 0.7;
    }
}
</style>
