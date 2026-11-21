<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Gift, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Lembrete da oferta de boas-vindas do onboarding (15% no anual) durante as
 * 24h em que ela vale. Gruda logo abaixo do cabeçalho do app. Fechar esconde
 * só até a próxima sessão do navegador.
 *
 * `variant="strip"` (mapa beta) troca o cartão por uma faixa de cor sólida de
 * lado a lado, encostada no cabeçalho: a frase à esquerda e o relógio à
 * direita. A faixa inteira leva à assinatura e não tem botão de fechar.
 */

const props = withDefaults(defineProps<{ variant?: 'card' | 'strip' }>(), { variant: 'card' });

const DISMISS_KEY = 'welcome-offer-dismissed';

const page = usePage();
const offer = computed(() => (page.props as { welcomeOffer?: { percent_off: number; expires_at: string } | null }).welcomeOffer ?? null);

const now = ref(Date.now());
const dismissed = ref(false);
let clock: ReturnType<typeof setInterval> | undefined;

const msLeft = computed(() => (offer.value ? new Date(offer.value.expires_at).getTime() - now.value : 0));
const visible = computed(() => msLeft.value > 0 && (props.variant === 'strip' || !dismissed.value));
const countdown = computed(() => {
    const ms = Math.max(0, msLeft.value);
    return [Math.floor(ms / 3_600_000), Math.floor((ms % 3_600_000) / 60_000), Math.floor((ms % 60_000) / 1000)]
        .map((n) => String(n).padStart(2, '0'))
        .join(':');
});

function dismiss() {
    dismissed.value = true;
    try {
        sessionStorage.setItem(DISMISS_KEY, '1');
    } catch {
        // Sem storage (aba privada): fecha só nesta tela.
    }
}

onMounted(() => {
    try {
        dismissed.value = sessionStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        dismissed.value = false;
    }
    if (offer.value) clock = setInterval(() => (now.value = Date.now()), 1000);
});

onBeforeUnmount(() => clearInterval(clock));
</script>

<template>
    <Transition name="offer-banner" appear>
        <Link
            v-if="visible && variant === 'strip'"
            :href="route('subscription.index')"
            data-welcome-offer
            class="sticky z-40 flex items-center justify-between gap-4 border-b-2 border-pink-200 bg-pink-100 px-5 py-3 text-pink-800 transition-colors hover:bg-pink-200/70 dark:border-pink-900 dark:bg-pink-950 dark:text-pink-200"
            style="top: var(--app-header-h, 64px)"
        >
            <span class="text-base font-bold leading-snug">Contagem regressiva final para {{ offer?.percent_off }}% de desconto!</span>
            <span class="shrink-0 font-mono text-lg tabular-nums">{{ countdown }}</span>
        </Link>
        <div v-else-if="visible" data-welcome-offer class="sticky z-40 mb-4" style="top: calc(var(--app-header-h, 64px) + 8px)">
            <div
                class="flex items-center gap-3 rounded-2xl border-2 border-pink-200 bg-pink-100 py-2.5 pl-3 pr-2 dark:border-pink-900 dark:bg-pink-950"
            >
                <span
                    class="offer-gift flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-pink-500 text-white"
                >
                    <Gift class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1 text-sm leading-tight">
                    <p class="font-bold text-pink-700 dark:text-pink-300">{{ offer?.percent_off }}% off no plano anual</p>
                    <p class="text-pink-800 dark:text-pink-200">
                        Termina em <span class="font-semibold tabular-nums">{{ countdown }}</span>
                    </p>
                </div>
                <Link
                    :href="route('subscription.index')"
                    class="mz-btn mz-sm mz-main shrink-0"
                >
                    Garantir
                </Link>
                <button
                    type="button"
                    class="mz-btn mz-xs mz-sq mz-ghost shrink-0 !text-pink-700 dark:!text-pink-300"
                    aria-label="Fechar lembrete da oferta"
                    @click="dismiss"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.offer-banner-enter-active {
    transition:
        transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1),
        opacity 0.3s;
}
.offer-banner-leave-active {
    transition:
        transform 0.25s,
        opacity 0.2s;
}
.offer-banner-enter-from,
.offer-banner-leave-to {
    opacity: 0;
    transform: translateY(-16px) scale(0.96);
}

.offer-gift {
    animation: offer-wiggle 3s ease-in-out infinite 1s;
}
@keyframes offer-wiggle {
    0%,
    82%,
    100% {
        transform: rotate(0);
    }
    86% {
        transform: rotate(-10deg);
    }
    92% {
        transform: rotate(8deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .offer-gift {
        animation: none;
    }
}
</style>
