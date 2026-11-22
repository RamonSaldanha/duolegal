<script setup lang="ts">
import { YEARLY, YEARLY_OFF, YEARLY_PER_MONTH, YEARLY_URL } from '@/lib/premium';
import { Link } from '@inertiajs/vue3';

// O plano anual como cartão escolhido (azul, com base) e o botão azul de assinar. Usado no
// convite do fim de fase e na tela sem energia. O que vier no slot fica entre o cartão e o
// botão (o vade-mecum, no convite).
defineProps<{ line?: string }>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="mz-choice is-selected price relative">
            <!-- Etiqueta suspensa na borda de cima, fora da linha do texto -->
            <span class="tag absolute -top-2.5 right-4">{{ YEARLY_OFF }}</span>
            <span class="min-w-0 flex-1 leading-tight">
                <span class="block text-base font-bold">Plus anual</span>
                <span class="block text-sm text-muted-foreground">{{ line ?? `${YEARLY_PER_MONTH} por mês` }}</span>
            </span>
            <span class="text-xl font-bold tabular-nums">{{ YEARLY }}</span>
        </div>
        <slot />
    </div>
    <Link :href="YEARLY_URL" class="mz-btn mz-lg cta mt-4 w-full">Assinar por {{ YEARLY }}/ano</Link>
</template>

<style scoped>
/* Azul do Premium: o mesmo do mapa (blue-500), com a base no blue-700 */
.tag,
.cta,
.price {
    --blue: #3b82f6;
    --blue-edge: #1d4ed8;
}
.cta {
    --e: var(--blue-edge);
    background: var(--blue);
    color: #fff;
    box-shadow: 0 var(--d) 0 var(--e);
}
@media (hover: hover) {
    .cta:hover {
        background: #2563eb;
    }
}
.cta:active {
    transform: translateY(var(--d));
    box-shadow: 0 0 0 var(--e);
}
.tag {
    border-radius: 999px;
    padding: 1px 8px;
    background: var(--blue);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}
.price {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 16px;
}
.price.is-selected {
    --c: var(--blue);
}
</style>
