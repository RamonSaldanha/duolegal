<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import BottomNavigation from '@/components/BottomNavigation.vue';
import type {
    CompletedSegmentData,
    ActiveSegmentData,
    PlayProgress,
    SubmitAnswerResponse,
    SegmentAnswer,
} from '@/types/legislation-play';
import { ref, onMounted, nextTick } from 'vue';
import { Trophy } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';
import Toaster from '@/components/ui/toast/Toaster.vue';
// @ts-expect-error: vue-rewards package does not provide proper type definitions
import { useReward } from 'vue-rewards';
import axios from 'axios';
import CompletedSegment from '@/components/legislation-play/CompletedSegment.vue';
import ActiveChallenge from '@/components/legislation-play/ActiveChallenge.vue';

const props = defineProps<{
    legislation: { uuid: string; title: string };
    completedSegments: CompletedSegmentData[];
    activeSegment: ActiveSegmentData | null;
    progress: PlayProgress;
    hasMoreAbove: boolean;
    allComplete: boolean;
    phaseId?: number | null;
    nextPhaseId?: number | null;
    phaseBlockUuids?: string[] | null;
}>();

// Determinar se estamos no modo fase (vindo do mapa) ou modo legacy
const isPhaseMode = !!props.phaseId;

const { toast } = useToast();
const page = usePage();

// Estado local
const completed = ref<CompletedSegmentData[]>([...props.completedSegments]);
const active = ref<ActiveSegmentData | null>(props.activeSegment);
const progress = ref<PlayProgress>({ ...props.progress });
const hasMore = ref(props.hasMoreAbove);
const isLoadingMore = ref(false);
const isSubmitting = ref(false);
const showCompletionModal = ref(props.allComplete);
const activeChallengeRef = ref<InstanceType<typeof ActiveChallenge> | null>(null);

// Refs do DOM
const scrollContainerRef = ref<HTMLElement | null>(null);
const sentinelRef = ref<HTMLElement | null>(null);
const activeRef = ref<HTMLElement | null>(null);

// Confetti
const { reward: confettiReward } = useReward('play-confetti', 'confetti', {
    lifetime: 300,
    elementCount: 80,
    spread: 80,
});

// Áudio de sucesso
const playSuccessAudio = () => {
    try {
        const audio = new Audio('/bell-win.wav');
        audio.volume = 0.7;
        audio.play().catch(() => {});
    } catch {
        // Silently fail
    }
};

// Scroll infinito para cima
let observer: IntersectionObserver | null = null;

onMounted(() => {
    // Scroll para o desafio ativo
    nextTick(() => {
        if (activeRef.value) {
            activeRef.value.scrollIntoView({ behavior: 'instant', block: 'center' });
        }
    });

    // IntersectionObserver no sentinel (root = scroll container)
    if (sentinelRef.value && scrollContainerRef.value) {
        observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore.value && !isLoadingMore.value) {
                    loadMoreAbove();
                }
            },
            { root: scrollContainerRef.value, threshold: 0.1 },
        );
        observer.observe(sentinelRef.value);
    }
});

async function loadMoreAbove() {
    if (isLoadingMore.value || !hasMore.value || completed.value.length === 0) return;
    isLoadingMore.value = true;

    const firstPosition = completed.value[0]?.position ?? 0;
    const container = scrollContainerRef.value!;
    const oldScrollHeight = container.scrollHeight;

    try {
        const { data } = await axios.get(
            route('play.completed', props.legislation.uuid),
            { params: { before_position: firstPosition, limit: 5 } },
        );

        if (data.segments.length > 0) {
            completed.value = [...data.segments, ...completed.value];
            hasMore.value = data.hasMore;

            // Preservar posição do scroll dentro do container
            await nextTick();
            const newScrollHeight = container.scrollHeight;
            container.scrollTop += newScrollHeight - oldScrollHeight;
        } else {
            hasMore.value = false;
        }
    } catch {
        // Silently fail, user can try scrolling again
    } finally {
        isLoadingMore.value = false;
    }
}

async function handleSubmit(data: {
    segment_uuid: string;
    answers: SegmentAnswer[];
    correct_count: number;
    total_count: number;
}) {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    try {
        const submitPayload: Record<string, unknown> = { ...data };
        // Se estamos no modo fase, enviar UUIDs dos blocos da fase para limitar o escopo
        if (isPhaseMode && props.phaseBlockUuids) {
            submitPayload.phase_block_uuids = props.phaseBlockUuids;
        }

        const response = await axios.post<SubmitAnswerResponse>(
            route('play.submit'),
            submitPayload,
        );

        const result = response.data;

        // Atualizar XP e vidas no header em tempo real
        if (result.user) {
            if (result.user.xp !== undefined) {
                page.props.auth.user.xp = result.user.xp;
            }
            if (result.user.lives !== undefined) {
                page.props.auth.user.lives = result.user.lives;
            }
        }

        if (result.should_redirect && result.redirect_url) {
            router.visit(result.redirect_url);
            return;
        }

        const passed = result.progress.percentage >= 70;

        if (passed) {
            // Confetti + áudio
            confettiReward();
            playSuccessAudio();

            // XP notification
            if (result.xp_gained > 0) {
                showXpGainedNotification(result.xp_gained);
            }

            // Esperar um momento para o jogador ver o resultado
            await new Promise((resolve) => setTimeout(resolve, 1500));

            // Transformar ativo em completado
            if (active.value) {
                const completedSeg: CompletedSegmentData = {
                    uuid: active.value.uuid,
                    original_text: active.value.original_text,
                    position: active.value.position,
                    segment_type: active.value.segment_type,
                    article_reference: active.value.article_reference,
                    structural_marker: active.value.structural_marker,
                    answers: result.answers,
                    is_completed: true,
                    best_score: result.progress.best_score,
                };
                completed.value.push(completedSeg);
            }

            // Próximo segmento
            if (result.next_segment) {
                active.value = result.next_segment;
                progress.value = {
                    current: progress.value.current + 1,
                    total: progress.value.total,
                    percentage: Math.round(((progress.value.current + 1) / progress.value.total) * 100),
                };

                // Reset e scroll para novo desafio
                await nextTick();
                activeChallengeRef.value?.reset();
                await nextTick();
                if (activeRef.value) {
                    activeRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                // Concluiu toda a legislação
                active.value = null;
                progress.value.percentage = 100;
                showCompletionModal.value = true;
            }
        } else {
            // Falhou — pode tentar novamente
            if (result.lost_life) {
                toast({
                    title: 'Vida perdida!',
                    description: `Você precisa acertar pelo menos 70%. Acertou ${data.correct_count} de ${data.total_count}.`,
                    variant: 'destructive',
                    duration: 4000,
                });
            }

            // Reset o challenge para tentar novamente
            await nextTick();
            activeChallengeRef.value?.reset();
        }
    } catch {
        toast({
            title: 'Erro',
            description: 'Não foi possível enviar sua resposta. Tente novamente.',
            variant: 'destructive',
            duration: 4000,
        });
    } finally {
        isSubmitting.value = false;
    }
}

function showXpGainedNotification(xpGained: number) {
    const notification = document.createElement('div');
    notification.textContent = `+${xpGained} XP`;
    notification.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 z-50 bg-purple-500 text-white px-4 py-2 rounded-lg font-bold text-lg shadow-lg';
    notification.style.animation = 'xpGain 2s ease-out forwards';

    const style = document.createElement('style');
    style.textContent = `
        @keyframes xpGain {
            0% { opacity: 0; transform: translate(-50%, 20px) scale(0.8); }
            30% { opacity: 1; transform: translate(-50%, -10px) scale(1.2); }
            70% { opacity: 1; transform: translate(-50%, -20px) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -40px) scale(0.8); }
        }
    `;

    document.head.appendChild(style);
    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification.parentNode) notification.parentNode.removeChild(notification);
        if (style.parentNode) style.parentNode.removeChild(style);
    }, 2000);
}


</script>

<template>
    <Head :title="legislation.title" />

    <div class="flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-gray-900">
        <Toaster position="top-right" class="z-[200]" />
        <span id="play-confetti" class="fixed top-1/2 left-1/2 z-[100] pointer-events-none"></span>

        <!-- Header -->
        <div class="shrink-0 z-50 bg-white dark:bg-gray-900">
            <AppHeader />
        </div>

        <!-- Área do jogo (preenche espaço restante) -->
        <div class="flex flex-1 flex-col min-h-0">
            <!-- Scroll container confinado (apenas texto) -->
            <div ref="scrollContainerRef" class="flex-1 overflow-y-auto min-h-0 px-3 md:px-4">
                <div class="w-full sm:w-[95%] lg:w-[50rem] mx-auto">
                    <!-- Sentinel para infinite scroll -->
                    <div ref="sentinelRef" class="h-1" />

                    <!-- Loading indicator -->
                    <div v-if="isLoadingMore" class="flex justify-center py-4">
                        <div class="h-6 w-6 border-2 border-gray-300 dark:border-gray-600 border-t-blue-500 rounded-full animate-spin" />
                    </div>

                    <!-- Segmentos completados -->
                    <CompletedSegment
                        v-for="seg in completed"
                        :key="seg.uuid"
                        :segment="seg"
                    />

                    <!-- Segmento ativo (apenas texto, controles via Teleport) -->
                    <div v-if="active" ref="activeRef">
                        <ActiveChallenge
                            ref="activeChallengeRef"
                            :segment="active"
                            controls-target="#play-controls"
                            @submit="handleSubmit"
                        />
                    </div>

                    <!-- Modal de conclusão -->
                    <div v-if="showCompletionModal" class="py-6 flex flex-col items-center">
                        <!-- Modo fase -->
                        <template v-if="isPhaseMode">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/40">
                                    <Trophy class="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                        Fase Concluída!
                                    </h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Você completou todos os blocos desta fase.
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <Link
                                    :href="route('play.map')"
                                    class="inline-flex items-center gap-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-bold px-6 py-2.5 rounded-lg border-2 border-gray-400 dark:border-gray-600 transition-all text-sm btn-press btn-press-gray"
                                >
                                    Voltar ao Mapa
                                </Link>
                                <Link
                                    v-if="nextPhaseId"
                                    :href="route('play.phase', { phaseId: nextPhaseId })"
                                    class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold px-6 py-2.5 rounded-lg border-2 border-green-700 transition-all text-sm btn-press btn-press-green"
                                >
                                    Próxima Fase
                                </Link>
                            </div>
                        </template>

                        <!-- Modo legislação (legacy) -->
                        <template v-else>
                            <div class="flex items-center gap-4 mb-4">
                                <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/40">
                                    <Trophy class="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                        Legislação Concluída!
                                    </h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Você completou todos os blocos desta legislação.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('play.map')"
                                class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold px-6 py-2.5 rounded-lg border-2 border-green-700 transition-all text-sm btn-press btn-press-green"
                            >
                                Voltar ao Mapa
                            </Link>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Área de controles fixa (teleport target) -->
            <div class="shrink-0 px-3 md:px-4 pb-2">
                <div id="play-controls" class="w-full sm:w-[95%] lg:w-[50rem] mx-auto" />
            </div>
        </div>

        <!-- Espaço reservado para o bottom nav fixo -->
        <div class="shrink-0 h-16" />
        <BottomNavigation />
    </div>
</template>

<style scoped>
.btn-press {
    transition: transform 0.1s ease, box-shadow 0.1s ease;
}

.btn-press-green {
    box-shadow: 0 3px 0 #15803d;
}
.btn-press-green:hover {
    transform: translateY(3px);
    box-shadow: none;
}

.btn-press-gray {
    box-shadow: 0 3px 0 #9ca3af;
}
.btn-press-gray:hover {
    transform: translateY(3px);
    box-shadow: none;
}
</style>
