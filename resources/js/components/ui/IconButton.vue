<template>
    <GameButton :variant="variant" :size="size" square :href="href" :aria-label="label" :title="label">
        <slot />
    </GameButton>
</template>

<script setup lang="ts">
import GameButton from '@/components/ui/GameButton.vue';

// Botão só de ícone: quadrado de 40 px (32 no compacto), canto de 12, cinza. O X sai do fluxo
// (fase, prova, onboarding) e a seta volta um passo. Ícone de 20 px no slot.
withDefaults(
    defineProps<{
        label: string;
        variant?: 'ghost' | 'tonal' | 'outline' | 'second' | 'main';
        size?: 'xs' | 'sm';
        href?: string;
    }>(),
    { variant: 'ghost', size: 'sm', href: undefined },
);
</script>
