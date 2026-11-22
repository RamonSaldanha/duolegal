<script setup lang="ts">
import AdSenseComponent from '@/components/AdSenseComponent.vue';
import GameButton from '@/components/ui/GameButton.vue';
import type { RewardAd, RewardAdResponse } from '@/types/beta-play';
import axios from 'axios';
import { Volume2, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * O anúncio da tela sem energia, em tela cheia: o nosso (o vídeo do Premium) ou o do Google,
 * como o servidor mandar (eles se revezam). Não tem botão de pegar o prêmio nem de fechar
 * enquanto passa: quando o vídeo acaba (ou a contagem do Google chega a zero) a energia é
 * creditada sozinha e só então aparecem o "Você ganhou" e o fechar. Igual ao app
 * (src/components/trail/RewardedAdOverlay.tsx).
 */
const props = defineProps<{ ad: RewardAd; rewardRoute: string }>();
const emit = defineEmits<{ close: [result: RewardAdResponse | null] }>();

type Stage = 'playing' | 'claiming' | 'done' | 'error';
const stage = ref<Stage>('playing');
const result = ref<RewardAdResponse | null>(null);
const errorText = ref('');

// O vídeo; se ele não carregar, uma contagem com o convite parado no lugar
const video = ref<HTMLVideoElement | null>(null);
const fallback = ref(props.ad.kind === 'house' && !props.ad.video_url);
const muted = ref(false);
const progress = ref(0);
const videoLeft = ref<number | null>(null);

// A contagem do Google (e a do vídeo que falhou), que para com a aba escondida
const countdown = ref(props.ad.seconds);
let timer: ReturnType<typeof setInterval> | null = null;
const counting = computed(() => props.ad.kind === 'google' || fallback.value);

const left = computed(() => (counting.value ? countdown.value : videoLeft.value));

function startCountdown() {
    if (timer) return;
    timer = setInterval(() => {
        if (document.hidden || stage.value !== 'playing') return;
        countdown.value -= 1;
        progress.value = 1 - countdown.value / props.ad.seconds;
        if (countdown.value <= 0) finish();
    }, 1000);
}

async function playVideo() {
    const el = video.value;
    if (!el) return;
    try {
        await el.play();
    } catch {
        // Sem som o navegador sempre deixa tocar; o botão do alto liga o som
        el.muted = true;
        muted.value = true;
        el.play().catch(() => onVideoError());
    }
}

function unmute() {
    if (!video.value) return;
    video.value.muted = false;
    muted.value = false;
}

function onTime() {
    const el = video.value;
    if (!el || !el.duration) return;
    progress.value = el.currentTime / el.duration;
    videoLeft.value = Math.max(0, Math.ceil(el.duration - el.currentTime));
}

function onVideoError() {
    if (fallback.value || stage.value !== 'playing') return;
    fallback.value = true;
    startCountdown();
}

async function finish() {
    if (stage.value !== 'playing') return;
    if (timer) clearInterval(timer);
    timer = null;
    progress.value = 1;
    stage.value = 'claiming';
    try {
        const response = await axios.post<RewardAdResponse>(props.rewardRoute);
        result.value = response.data;
        stage.value = 'done';
    } catch (error) {
        errorText.value =
            axios.isAxiosError(error) && error.response?.data?.message
                ? error.response.data.message
                : 'Não foi possível dar a energia. Tente de novo.';
        stage.value = 'error';
    }
}

const prize = computed(() => {
    if (!result.value) return '';
    return result.value.gained > 0 ? `+${result.value.gained} de energia` : 'Sua energia já está cheia';
});

function close() {
    if (stage.value === 'playing' || stage.value === 'claiming') return;
    emit('close', result.value);
}

onMounted(() => {
    document.documentElement.style.overflow = 'hidden';
    if (counting.value) startCountdown();
    else playVideo();
});
onUnmounted(() => {
    document.documentElement.style.overflow = '';
    if (timer) clearInterval(timer);
});
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-[70] flex flex-col"
            :class="ad.kind === 'house' && !fallback ? 'bg-black' : 'bg-background'"
            role="dialog"
            aria-modal="true"
            aria-label="Anúncio"
        >
            <!-- O andamento e o tempo que falta; o fechar só no fim -->
            <div class="absolute inset-x-0 top-0 z-10 h-1 bg-white/15">
                <div class="h-full bg-brand transition-[width] duration-300 ease-linear" :style="{ width: `${progress * 100}%` }" />
            </div>
            <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between gap-2 px-3 pt-4">
                <span class="rounded-md bg-black/70 px-2.5 py-1 text-xs font-bold text-white">Anúncio</span>
                <div class="flex items-center gap-2">
                    <button
                        v-if="muted && stage === 'playing'"
                        type="button"
                        class="flex h-9 items-center gap-1.5 rounded-full bg-black/70 px-3 text-xs font-bold text-white"
                        @click="unmute"
                    >
                        <Volume2 class="h-4 w-4" /> Ligar o som
                    </button>
                    <span
                        v-if="stage === 'playing' && left !== null"
                        class="rounded-full bg-black/70 px-3 py-1.5 text-xs font-bold tabular-nums text-white"
                    >
                        Recompensa em {{ left }}s
                    </span>
                    <button
                        v-if="stage === 'done' || stage === 'error'"
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-full bg-black/70 text-white"
                        aria-label="Fechar o anúncio"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- O nosso: o vídeo do Premium -->
            <video
                v-if="ad.kind === 'house' && !fallback"
                ref="video"
                :src="ad.video_url ?? undefined"
                class="h-full w-full object-contain"
                playsinline
                preload="auto"
                @timeupdate="onTime"
                @ended="finish"
                @error="onVideoError"
            />

            <!-- O nosso, quando o vídeo não carrega -->
            <div v-else-if="ad.kind === 'house'" class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
                <img src="/img/premium-nomeado.gif" alt="" class="w-48" />
                <p class="text-2xl font-bold text-foreground">Memorize Direito Plus</p>
                <p class="text-muted-foreground">Energia infinita, todas as leis, fantasias exclusivas e sem anúncios.</p>
            </div>

            <!-- O do Google -->
            <div v-else class="flex flex-1 items-center justify-center overflow-y-auto px-4 pb-6 pt-16">
                <div class="w-full max-w-2xl">
                    <AdSenseComponent :min-width="300" :min-height="250" />
                </div>
            </div>

            <!-- O prêmio, dado sozinho no fim -->
            <Transition name="prize">
                <div
                    v-if="stage === 'done' || stage === 'error'"
                    class="absolute inset-x-0 bottom-0 z-10 px-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
                >
                    <div class="mx-auto flex w-full max-w-md flex-col gap-3 rounded-3xl bg-card p-5 shadow-xl">
                        <div v-if="stage === 'done'" class="flex items-center gap-3">
                            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-soft text-brand-soft-ink">
                                <svg
                                    viewBox="0 0 24 24"
                                    class="h-7 w-7"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="m11 7-3 5h4l-3 5" />
                                    <path d="M14.856 6H16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.935" />
                                    <path d="M5.14 18H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h2.936" />
                                    <path d="M22 14v-4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-foreground">{{ result && result.gained > 0 ? 'Você ganhou!' : 'Pronto!' }}</p>
                                <p class="text-sm font-semibold text-muted-foreground">{{ prize }}</p>
                            </div>
                        </div>
                        <p v-else class="text-base font-semibold text-foreground">{{ errorText }}</p>
                        <GameButton variant="main" size="lg" class="w-full" @click="close">Fechar</GameButton>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
.prize-enter-active {
    transition:
        transform 0.35s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.2s;
}
.prize-enter-from {
    transform: translateY(40px);
    opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
    .prize-enter-active {
        transition: none;
    }
}
</style>
