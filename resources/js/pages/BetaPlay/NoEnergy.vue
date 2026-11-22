<script setup lang="ts">
import AdComponent from '@/components/AdComponent.vue';
import GameButton from '@/components/ui/GameButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { EnergyState } from '@/types/beta-play';
import { Head } from '@inertiajs/vue3';
import { BatteryCharging, BatteryLow, RotateCcw } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

// Sem energia (mapa beta): quanto falta para o próximo +1, a revisão sugerida (abre sem
// energia e o combo dela recarrega), o anúncio e o Premium, que tem energia infinita.
const props = defineProps<{
    energy: EnergyState;
    review: { id: number; legislation_title: string } | null;
    ad_reward: number;
}>();

const PHASE_OPEN = 10;

const now = ref(Date.now());
const ticker = setInterval(() => (now.value = Date.now()), 30_000);
onUnmounted(() => clearInterval(ticker));

const canPlay = computed(() => props.energy.infinite || props.energy.value >= PHASE_OPEN);
const percent = computed(() => Math.min(100, (props.energy.value / props.energy.max) * 100));

const nextIn = computed(() => {
    if (!props.energy.next_at) return null;
    const minutes = Math.ceil((Date.parse(props.energy.next_at) - now.value) / 60_000);
    return minutes > 0 ? `${minutes} min` : 'instantes';
});

const ad = ref<InstanceType<typeof AdComponent> | null>(null);
</script>

<template>
    <Head title="Sem energia" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-md flex-col px-4 py-8">
            <div class="flex flex-col items-center text-center">
                <div class="battery" :class="{ 'is-ok': canPlay }">
                    <BatteryCharging v-if="canPlay" class="h-14 w-14" :stroke-width="1.75" />
                    <BatteryLow v-else class="h-14 w-14" :stroke-width="1.75" />
                </div>

                <h1 class="mt-4 text-2xl font-bold text-foreground">
                    {{ energy.infinite ? 'Sua energia é infinita' : canPlay ? 'Energia de volta!' : 'Sua energia acabou' }}
                </h1>
                <p v-if="!energy.infinite" class="mt-2 text-muted-foreground">
                    <template v-if="canPlay">Você já pode abrir uma fase nova.</template>
                    <template v-else>
                        Uma fase nova precisa de {{ PHASE_OPEN }} de energia.
                        <template v-if="nextIn"> Mais 1 em {{ nextIn }}.</template>
                    </template>
                </p>

                <!-- A barra -->
                <div v-if="!energy.infinite" class="mt-5 w-full">
                    <div
                        class="h-4 w-full overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-valuenow="energy.value"
                        aria-valuemin="0"
                        :aria-valuemax="energy.max"
                    >
                        <div class="fill h-full rounded-full" :style="{ width: `${percent}%` }" />
                    </div>
                    <p class="mt-1.5 text-right text-sm font-bold tabular-nums text-muted-foreground">{{ energy.value }} de {{ energy.max }}</p>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3">
                <template v-if="canPlay">
                    <GameButton variant="main" size="lg" class="w-full" :href="route('beta.play.map')">Voltar ao mapa</GameButton>
                </template>
                <template v-else>
                    <GameButton v-if="review" variant="main" size="lg" class="w-full" :href="route('beta.play.phase', review.id)">
                        <RotateCcw class="h-5 w-5" /> Revisar uma fase
                    </GameButton>
                    <p v-if="review" class="-mt-1 text-center text-sm text-muted-foreground">
                        {{ review.legislation_title }}. A revisão abre sem energia e 5 acertos seguidos devolvem 2.
                    </p>

                    <GameButton variant="second" size="lg" class="w-full" @click="ad?.startAdExperience()">
                        Assistir anúncio (+{{ ad_reward }})
                    </GameButton>
                    <GameButton variant="tonal-brand" class="w-full" href="/subscription?plan=yearly">Energia infinita com o Premium</GameButton>
                    <GameButton variant="ghost" size="sm" class="w-full" :href="route('beta.play.map')">Voltar ao mapa</GameButton>
                </template>
            </div>
        </div>

        <AdComponent
            ref="ad"
            :reward-route="route('beta.play.energy.reward')"
            :redirect-route="route('beta.play.energy')"
            :prize="`${ad_reward} de energia`"
            claim-label="Pegar a energia"
        />
    </AppLayout>
</template>

<style scoped>
.battery {
    display: grid;
    place-items: center;
    width: 6.5rem;
    height: 6.5rem;
    border-radius: 999px;
    background: hsl(var(--muted));
    color: hsl(var(--muted-foreground));
}
.battery.is-ok {
    background: var(--mz-brand-soft);
    color: var(--mz-brand-soft-ink);
}
.fill {
    background: hsl(var(--primary));
    transition: width 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
</style>
