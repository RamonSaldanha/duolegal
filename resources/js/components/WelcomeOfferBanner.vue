<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Gift, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Lembrete da oferta de boas-vindas do onboarding (15% no anual) durante as
 * 24h em que ela vale. Gruda logo abaixo do cabeçalho do app. Fechar esconde
 * só até a próxima sessão do navegador.
 */

const DISMISS_KEY = 'welcome-offer-dismissed';

const page = usePage();
const offer = computed(() => (page.props as { welcomeOffer?: { percent_off: number; expires_at: string } | null }).welcomeOffer ?? null);

const now = ref(Date.now());
const dismissed = ref(false);
let clock: ReturnType<typeof setInterval> | undefined;

const msLeft = computed(() => (offer.value ? new Date(offer.value.expires_at).getTime() - now.value : 0));
const visible = computed(() => !dismissed.value && msLeft.value > 0);
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
        <div v-if="visible" class="sticky z-40 mb-4" style="top: calc(var(--app-header-h, 64px) + 8px)">
            <div
                class="flex items-center gap-3 rounded-2xl border-2 border-pink-200 bg-white py-2.5 pl-3 pr-2 shadow-[0_4px_0_#fbcfe8] dark:border-pink-900 dark:bg-gray-800 dark:shadow-[0_4px_0_#0f0f0f]"
            >
                <span
                    class="offer-gift flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-pink-100 text-pink-500 dark:bg-pink-950/60 dark:text-pink-300"
                >
                    <Gift class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1 text-sm leading-tight">
                    <p class="font-bold text-gray-900 dark:text-white">{{ offer?.percent_off }}% off no plano anual</p>
                    <p class="text-gray-500 dark:text-gray-400">
                        Termina em <span class="font-semibold tabular-nums text-pink-600 dark:text-pink-300">{{ countdown }}</span>
                    </p>
                </div>
                <Link
                    :href="route('subscription.index')"
                    class="shrink-0 rounded-xl border-2 border-green-600 bg-green-500 px-3 py-2 text-xs font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_#15803d] transition hover:bg-green-400 active:translate-y-[3px] active:shadow-none"
                >
                    Garantir
                </Link>
                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
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
