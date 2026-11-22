<script setup lang="ts">
// O traço de marca-texto das telas do jogo (fim de fase, convite, sem energia, assinatura):
// passa da esquerda para a direita embaixo do texto, depois de `delay` ms. Sem movimento, o
// traço já aparece pronto.
withDefaults(defineProps<{ delay?: number }>(), { delay: 300 });
</script>

<template>
    <span class="mark" :style="{ '--mark-delay': `${delay}ms` }"><slot /></span>
</template>

<style scoped>
.mark {
    position: relative;
    z-index: 0;
    display: inline-block;
    white-space: nowrap;
}
.mark::before {
    content: '';
    position: absolute;
    z-index: -1;
    left: -4px;
    right: -4px;
    bottom: 0.08em;
    height: 0.42em;
    border-radius: 3px;
    background: var(--mz-brand-mark);
    transform-origin: left center;

    @media (prefers-reduced-motion: no-preference) {
        animation: mark-swipe 0.45s var(--mark-delay) cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}

@keyframes mark-swipe {
    from {
        scale: 0 1;
    }
    to {
        scale: 1 1;
    }
}
</style>
