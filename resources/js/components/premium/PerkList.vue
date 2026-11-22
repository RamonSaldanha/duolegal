<script setup lang="ts">
import { PERKS } from '@/lib/premium';

// As vantagens do Premium, sem ícone nem descrição: etiquetas que entram uma depois da outra.
// O atraso de cada uma vem da posição dela (sibling-index(); onde não há, um --i posto aqui).
const props = withDefaults(defineProps<{ perks?: readonly string[]; delay?: number }>(), { perks: () => PERKS, delay: 200 });

const nativeIndex = typeof CSS !== 'undefined' && CSS.supports('animation-delay: calc(sibling-index() * 0.1s)');
const indexStyle = (i: number) => (nativeIndex ? undefined : { '--i': i + 1 });
</script>

<template>
    <ul class="perks" :style="{ '--perk-base': `${props.delay}ms` }">
        <li v-for="(perk, i) in perks" :key="perk" class="perk" :style="indexStyle(i)">{{ perk }}</li>
    </ul>
</template>

<style scoped>
.perks {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
}
.perk {
    --stagger: 90ms;
    border-radius: 999px;
    padding: 5px 12px;
    background: hsl(var(--muted));
    color: hsl(var(--foreground));
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.25;

    @media (prefers-reduced-motion: no-preference) {
        animation: perk-in 0.36s cubic-bezier(0.22, 1, 0.36, 1) both;
        animation-delay: calc(var(--perk-base) + var(--i, 1) * var(--stagger));

        @supports (animation-delay: calc(sibling-index() * 0.1s)) {
            animation-delay: calc(var(--perk-base) + sibling-index() * var(--stagger));
        }
    }
}

@keyframes perk-in {
    from {
        opacity: 0;
        translate: 0 8px;
        scale: 0.94;
    }
}
</style>
