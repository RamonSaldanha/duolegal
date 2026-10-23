<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Heart, Loader2, Zap } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import GiftBox from '@/components/GiftBox.vue';

/**
 * Página do presente que vai no lembrete por e-mail. Abre sem login, pelo
 * link do e-mail; o botão "Abrir presente" é que entrega (POST).
 */
interface Gift {
    uuid: string;
    type: 'lives' | 'xp_boost';
    status: 'available' | 'claimed' | 'expired';
    lives: number;
    boost_days: number;
    boost_percent: number;
    boost_until: string | null;
}

const props = defineProps<{
    gift: Gift;
    name: string;
    justOpened: boolean;
}>();

const opening = ref(false);
const isBoost = computed(() => props.gift.type === 'xp_boost');
const opened = computed(() => props.gift.status === 'claimed');

const boostUntil = computed(() => {
    if (!props.gift.boost_until) {
        return null;
    }
    const date = new Date(props.gift.boost_until);
    return `${date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} às ${date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
});

const open = () => {
    opening.value = true;
    router.post(route('gift.claim', props.gift.uuid), {}, { preserveScroll: true, onFinish: () => (opening.value = false) });
};

// Confete de formas chapadas, só na hora que abre.
const confetti = Array.from({ length: 18 }, (_, i) => ({
    left: `${(i * 37) % 100}%`,
    delay: `${(i % 6) * 0.08}s`,
    color: ['#22c55e', '#3b82f6', '#facc15', '#ef4444', '#a855f7', '#f97316'][i % 6],
    shape: i % 3 === 0 ? 'rounded-full' : 'rounded-sm',
    rotate: `${(i * 47) % 360}deg`,
}));
</script>

<template>
    <Head title="Seu presente" />

    <div class="relative flex min-h-[100dvh] flex-col overflow-hidden bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <header class="flex justify-center px-4 pt-6">
            <Link :href="route('home')" aria-label="Memorize Direito">
                <img src="/img/logomemorizeblack.svg" alt="Memorize Direito" class="h-12 w-auto dark:hidden" />
                <img src="/img/logomemorize.svg" alt="Memorize Direito" class="hidden h-12 w-auto dark:block" />
            </Link>
        </header>

        <div v-if="justOpened" class="pointer-events-none absolute inset-x-0 top-0 h-0" aria-hidden="true">
            <span
                v-for="(piece, i) in confetti"
                :key="i"
                :class="['gift-confetti absolute block h-3 w-2', piece.shape]"
                :style="{ left: piece.left, background: piece.color, animationDelay: piece.delay, '--spin': piece.rotate }"
            />
        </div>

        <main class="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center px-4 py-8">
            <div
                class="w-full rounded-3xl border-2 border-b-[6px] border-gray-200 bg-white px-6 pb-7 pt-4 text-center dark:border-gray-700 dark:bg-gray-800"
            >
                <GiftBox
                    :variant="isBoost ? 'xp' : 'lives'"
                    :opened="opened"
                    :class="['mx-auto h-56 w-56', gift.status === 'expired' && 'opacity-50 grayscale']"
                />

                <!-- Fechado -->
                <template v-if="gift.status === 'available'">
                    <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">Oi, {{ name }}! Tem presente pra você</h1>
                    <p class="mt-3 text-base text-gray-600 dark:text-gray-300">
                        <template v-if="isBoost">
                            Dentro tem <strong class="text-purple-600 dark:text-purple-300">{{ gift.boost_percent }}% a mais de XP</strong> por
                            {{ gift.boost_days }} dias. Os dias começam a contar quando você abrir.
                        </template>
                        <template v-else>
                            Dentro tem <strong class="text-red-600 dark:text-red-400">{{ gift.lives }} vidas novinhas</strong> pra você voltar sem
                            medo de errar.
                        </template>
                    </p>
                    <button type="button" class="gift-cta mt-6" :disabled="opening" @click="open">
                        <Loader2 v-if="opening" class="h-5 w-5 animate-spin" />
                        Abrir presente
                    </button>
                </template>

                <!-- Aberto -->
                <template v-else-if="gift.status === 'claimed'">
                    <div
                        :class="[
                            'gift-reward mx-auto -mt-2 inline-flex items-center gap-2 rounded-2xl border-2 border-b-4 px-5 py-2 text-2xl font-black',
                            isBoost
                                ? 'border-purple-200 bg-purple-50 text-purple-600 dark:border-purple-900 dark:bg-purple-950/40 dark:text-purple-300'
                                : 'border-red-200 bg-red-50 text-red-600 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400',
                        ]"
                    >
                        <template v-if="isBoost"><Zap class="h-6 w-6 fill-current" /> XP +{{ gift.boost_percent }}%</template>
                        <template v-else><Heart class="h-6 w-6 fill-current" /> +{{ gift.lives }} vidas</template>
                    </div>
                    <h1 class="mt-4 text-2xl font-extrabold sm:text-3xl">
                        {{ justOpened ? 'Presente aberto!' : 'Esse presente já foi aberto' }}
                    </h1>
                    <p class="mt-3 text-base text-gray-600 dark:text-gray-300">
                        <template v-if="isBoost">
                            <template v-if="boostUntil"
                                >O boost tá ligado até {{ boostUntil }}. Cada lacuna certa rende mais, no site e no app.</template
                            >
                            <template v-else>O boost já acabou, mas o jogo continua aí te esperando.</template>
                        </template>
                        <template v-else>As vidas já estão na sua conta, no site e no app. Agora é só jogar.</template>
                    </p>
                    <Link :href="route('play.map')" class="gift-cta mt-6">Bora jogar</Link>
                </template>

                <!-- Expirado -->
                <template v-else>
                    <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">Esse presente expirou</h1>
                    <p class="mt-3 text-base text-gray-600 dark:text-gray-300">
                        Mas relaxa: o lembrete mais novo que mandamos tem outro presente te esperando. Dá uma olhada no seu e-mail.
                    </p>
                    <Link :href="route('play.map')" class="gift-cta mt-6">Bora jogar</Link>
                </template>
            </div>
        </main>
    </div>
</template>

<style scoped>
/* O mesmo botão verde do onboarding e do jogo. */
.gift-cta {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.5rem;
    border: 4px solid #15803d;
    background: #22c55e;
    padding: 0.75rem 1.5rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 4px 0 #15803d;
    transition:
        transform 0.1s,
        box-shadow 0.1s,
        background 0.1s;
}
.gift-cta:hover:not(:disabled) {
    background: #16a34a;
    transform: translateY(2px);
    box-shadow: 0 2px 0 #15803d;
}
.gift-cta:active:not(:disabled) {
    transform: translateY(4px);
    box-shadow: none;
}
.gift-cta:disabled {
    cursor: wait;
    opacity: 0.8;
}

.gift-reward {
    animation: reward-pop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.25s both;
}

.gift-confetti {
    top: -1rem;
    animation: confetti-fall 1.8s ease-in forwards;
}

@keyframes reward-pop {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes confetti-fall {
    from {
        transform: translateY(0) rotate(0deg);
        opacity: 1;
    }
    to {
        transform: translateY(100dvh) rotate(var(--spin));
        opacity: 0.2;
    }
}

@media (prefers-reduced-motion: reduce) {
    .gift-reward,
    .gift-confetti {
        animation: none;
    }
    .gift-confetti {
        display: none;
    }
}
</style>
