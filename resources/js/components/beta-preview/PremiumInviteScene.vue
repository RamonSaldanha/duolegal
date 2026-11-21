<script setup lang="ts">
import GameButton from '@/components/ui/GameButton.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

// PRÉVIA (ainda não usada no jogo): convite ao Premium depois do fim de fase, para quem não assina.
// Cabe numa tela só, sem rolar: a frase de efeito (o fim troca sozinho, com marca-texto), o preço
// do anual ao lado do vade-mecum impresso, o botão de assinar e o "Prefiro não agora". O GIF
// encolhe para caber o resto.
const emit = defineEmits<{ skip: [] }>();

const endings = ['joga sem parar.', 'decora de vez.', 'avança mais rápido.', 'passa na prova.', 'é nomeado.'];
const index = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;
let start: ReturnType<typeof setTimeout> | undefined;

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    start = setTimeout(() => {
        timer = setInterval(() => (index.value = (index.value + 1) % endings.length), 2400);
    }, 1600);
});
onUnmounted(() => {
    clearTimeout(start);
    clearInterval(timer);
});

// Preço do anual: R$ 107,91 (R$ 8,99/mês, 25% abaixo de 12x o mensal de R$ 11,99). Na tela de verdade vem
// do Stripe, como lá.
const YEARLY = 'R$ 107,91';
const YEARLY_PER_MONTH = 'R$ 8,99';
// Referência de preço de um vade-mecum impresso atualizado (confirmar antes de publicar)
const VADE_MECUM = 'R$ 200+';
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col overflow-y-auto bg-background">
        <div class="mx-auto flex min-h-0 w-full max-w-md flex-1 flex-col px-4 pb-3 pt-2">
            <!-- Boneca e frase num bloco só, centralizado no espaço que sobra. A boneca (mesma moldura
                 do fim de fase) encolhe descontando a frase e nunca é cortada; em tela baixa demais,
                 a página rola -->
            <div class="hero-room flex min-h-40 flex-1 flex-col items-center justify-center">
                <div class="hero-gif">
                    <img src="/img/premium-nomeado.gif" alt="" class="w-full" />
                </div>
                <h1 class="text-center text-[1.375rem] font-bold leading-[1.2] text-foreground">
                    Com Premium você
                    <span class="block" aria-live="polite">
                        <Transition name="swap" mode="out-in">
                            <span :key="index" class="mark">{{ endings[index] }}</span>
                        </Transition>
                    </span>
                </h1>
            </div>

            <div class="mt-4 flex flex-col gap-2">
                <div class="mz-choice is-selected price relative">
                    <!-- Etiqueta suspensa na borda de cima, fora da linha do texto -->
                    <span class="tag absolute -top-2.5 right-4">-25%</span>
                    <span class="min-w-0 flex-1 leading-tight">
                        <span class="block text-base font-bold">Premium anual</span>
                        <span class="block text-sm text-muted-foreground">{{ YEARLY_PER_MONTH }} por mês</span>
                    </span>
                    <span class="text-xl font-bold tabular-nums">{{ YEARLY }}</span>
                </div>
                <div class="price price-anchor">
                    <span class="min-w-0 flex-1 leading-tight">
                        <span class="block text-sm font-bold text-muted-foreground">Vade-mecum impresso</span>
                        <span class="block text-xs text-muted-foreground">Fica velho na próxima lei</span>
                    </span>
                    <span class="text-base font-bold tabular-nums text-muted-foreground line-through">{{ VADE_MECUM }}</span>
                </div>
            </div>

            <Link href="/subscription?plan=yearly" class="mz-btn mz-lg cta mt-4 w-full">Assinar por {{ YEARLY }}/ano</Link>
            <GameButton variant="ghost" size="sm" class="mt-1 w-full" @click="emit('skip')">Prefiro não agora</GameButton>
        </div>
    </div>
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
/* Moldura das ilustrações das telas de fim (igual no fim de fase) */
.hero-room {
    container-type: size;
}
.hero-gif {
    /* 3.5rem: a frase de duas linhas embaixo dela */
    width: min(15rem, 32vh, calc(100cqh - 3.5rem));
    aspect-ratio: 1;
    overflow: hidden;
}
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
.price-anchor {
    padding: 8px 14px;
    border: 2px dashed hsl(var(--border));
}

.swap-enter-active,
.swap-leave-active {
    transition:
        opacity 0.2s,
        translate 0.2s cubic-bezier(0.22, 1, 0.36, 1);
}
.swap-enter-from {
    opacity: 0;
    translate: 0 10px;
}
.swap-leave-to {
    opacity: 0;
    translate: 0 -10px;
}

@media (prefers-reduced-motion: no-preference) {
    .mark::before {
        animation: swipe 0.4s 0.15s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}
@media (prefers-reduced-motion: reduce) {
    .swap-enter-active,
    .swap-leave-active {
        transition: none;
    }
}

@keyframes swipe {
    from {
        scale: 0 1;
    }
    to {
        scale: 1 1;
    }
}
</style>
