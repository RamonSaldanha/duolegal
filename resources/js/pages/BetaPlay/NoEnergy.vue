<script setup lang="ts">
import RewardedAd from '@/components/beta-play/RewardedAd.vue';
import PremiumYearly from '@/components/beta-preview/PremiumYearly.vue';
import EnergyMeter from '@/components/EnergyMeter.vue';
import GameButton from '@/components/ui/GameButton.vue';
import IconButton from '@/components/ui/IconButton.vue';
import { YEARLY_PER_MONTH } from '@/lib/premium';
import type { EnergyState, RewardAd } from '@/types/beta-play';
import { Head, router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

// Sem energia (mapa beta), no mesmo desenho do convite Premium do fim de fase: o corredor
// cansado e a frase com marca-texto, quando volta o próximo +1, o Premium anual (energia
// infinita), o anúncio (+3, até 3 por dia, em tela cheia: o nosso e o do Google se revezam) e
// esperar a recarga. Cabe numa tela só; o GIF encolhe para caber.
const props = defineProps<{
    energy: EnergyState;
    ad_reward: number;
    ads_left: number;
    ad: RewardAd;
}>();

const PHASE_OPEN = 12;

const now = ref(Date.now());
const ticker = setInterval(() => (now.value = Date.now()), 30_000);
onUnmounted(() => clearInterval(ticker));

const canPlay = computed(() => props.energy.infinite || props.energy.value >= PHASE_OPEN);

const nextIn = computed(() => {
    if (!props.energy.next_at) return null;
    const minutes = Math.ceil((Date.parse(props.energy.next_at) - now.value) / 60_000);
    return minutes > 0 ? `${minutes} min` : 'instantes';
});

const watching = ref(false);

// Depois do anúncio, a tela pega de novo a energia e o próximo anúncio
function adClosed() {
    watching.value = false;
    router.reload({ only: ['energy', 'ads_left', 'ad'] });
}

function backToMap() {
    router.visit(route('beta.play.map'));
}
</script>

<template>
    <Head title="Sem energia" />

    <div class="flex h-dvh flex-col bg-background">
        <!-- Topo: voltar e a energia -->
        <div class="shrink-0 px-3 pt-4">
            <div class="mx-auto flex w-full max-w-md items-center justify-between">
                <IconButton label="Voltar ao mapa" class="-ml-2" @click="backToMap">
                    <X class="h-6 w-6" />
                </IconButton>
                <EnergyMeter :energy="energy" pill />
            </div>
        </div>

        <div class="relative flex min-h-0 flex-1 flex-col overflow-y-auto">
            <div class="mx-auto flex min-h-0 w-full max-w-md flex-1 flex-col px-4 pb-3 pt-2">
                <!-- O corredor e a frase num bloco só, centralizado no espaço que sobra -->
                <div class="hero-room flex min-h-40 flex-1 flex-col items-center justify-center">
                    <div class="hero-gif">
                        <img src="/img/sem-energia.gif" alt="" class="w-full" />
                    </div>
                    <h1 class="text-center text-[1.375rem] font-bold leading-[1.2] text-foreground">
                        Sua energia <span class="mark">{{ canPlay ? 'voltou!' : 'acabou.' }}</span>
                    </h1>
                    <p class="mt-1 text-center text-sm text-muted-foreground">
                        <template v-if="canPlay">Já dá para abrir uma fase nova.</template>
                        <template v-else
                            >Ela volta sozinha, 1 por hora<template v-if="nextIn"> (a próxima em {{ nextIn }})</template>.</template
                        >
                    </p>
                </div>

                <template v-if="canPlay">
                    <GameButton variant="main" size="lg" class="mt-4 w-full" @click="backToMap">Voltar ao mapa</GameButton>
                </template>
                <template v-else>
                    <div class="mt-4 flex flex-col">
                        <PremiumYearly :line="`Energia infinita · ${YEARLY_PER_MONTH} por mês`" />
                    </div>
                    <GameButton v-if="ads_left > 0" variant="second" size="lg" class="mt-3 w-full" @click="watching = true">
                        Assistir anúncio (+{{ ad_reward }})
                    </GameButton>
                    <p class="mt-2 text-center text-xs font-semibold text-muted-foreground">
                        {{
                            ads_left > 0
                                ? `${ads_left === 1 ? 'Resta 1 anúncio' : `Restam ${ads_left} anúncios`} hoje`
                                : 'Os anúncios de hoje acabaram. Volte amanhã.'
                        }}
                    </p>
                    <GameButton variant="ghost" size="sm" class="mt-1 w-full" @click="backToMap">Esperar a recarga</GameButton>
                </template>
            </div>
        </div>

        <RewardedAd v-if="watching" :ad="ad" :reward-route="route('beta.play.energy.reward')" @close="adClosed" />
    </div>
</template>

<style scoped>
/* Moldura do GIF, da mesma altura das ilustrações do fim de fase e do convite. O desenho é
   mais alto que largo: a moldura corta o vazio de cima (17,94% da largura) e fica com a
   proporção 418:540 */
.hero-room {
    container-type: size;
}
.hero-gif {
    /* 4.75rem: a frase e a linha da recarga embaixo dele */
    width: min(11.6rem, 24.8vh, calc((100cqh - 4.75rem) * 0.774));
    aspect-ratio: 418 / 540;
    overflow: hidden;
}
.hero-gif img {
    margin-top: -17.94%;
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
@media (prefers-reduced-motion: no-preference) {
    .mark::before {
        animation: swipe 0.4s 0.3s cubic-bezier(0.22, 1, 0.36, 1) both;
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
