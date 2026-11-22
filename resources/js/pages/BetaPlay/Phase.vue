<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { BatteryCharging, X, Zap } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
// @ts-expect-error: vue-rewards package does not provide proper type definitions
import ComboBurst from '@/components/beta-play/ComboBurst.vue';
import PhaseEnd from '@/components/beta-preview/PhaseEnd.vue';
import EnergyMeter from '@/components/EnergyMeter.vue';
import ActiveChallenge from '@/components/legislation-play/ActiveChallenge.vue';
import CompletedSegment from '@/components/legislation-play/CompletedSegment.vue';
import ResultBar from '@/components/ResultBar.vue';
import GameButton from '@/components/ui/GameButton.vue';
import IconButton from '@/components/ui/IconButton.vue';
import { playPhaseComplete } from '@/lib/sounds';
import type { BetaFinishResponse, EnergyCombo, EnergySpend, EnergyState } from '@/types/beta-play';
import type { ActiveSegmentData, CompletedSegmentData, DisciplineLevelUp, SegmentAnswer } from '@/types/legislation-play';
import { requiredCorrect } from '@/types/legislation-play';
import { useReward } from 'vue-rewards';

interface ChallengeResult {
    segment_uuid: string;
    answers: SegmentAnswer[];
    correct_count: number;
    total_count: number;
}

const props = defineProps<{
    legislation: { uuid: string; title: string };
    phaseId: number;
    phaseNumber: number;
    phasesPerGroup: number;
    isReview: boolean;
    challenges: ActiveSegmentData[];
    phaseBlocks: CompletedSegmentData[];
    earlierBlocks: CompletedSegmentData[];
    hasMoreAbove: boolean;
    energy: EnergyState;
}>();

const page = usePage();
const authUser = computed(() => page.props.auth.user as { xp: number; energy?: EnergyState | null });
// Quem assina pula o convite Premium do fim de fase
const isSubscriber = computed(() => Boolean((page.props.auth.user as { has_infinite_lives?: boolean }).has_infinite_lives));

// A fase inteira fica no navegador: nada é gravado antes do último desafio.
const queue = ref<ActiveSegmentData[]>([...props.challenges]);
const passed = ref<Record<string, ChallengeResult>>({});
const attempt = ref(0);
const lastResult = ref<(ChallengeResult & { passed: boolean }) | null>(null);
const stage = ref<'playing' | 'saving' | 'failed-to-save' | 'done'>('playing');

// Energia: cada bloco verificado gasta na hora; 3 certos seguidos devolvem 1 (combo).
// Se ela acaba no meio da fase nova, a pessoa sai e perde a fase.
const energy = ref<EnergyState>(props.energy);
const combo = ref<EnergyCombo | null>(null);
// A energia com o combo espera o raiozinho chegar na bateria (ComboBurst)
let comboEnergy: EnergyState | null = null;
const outOfEnergy = ref(false);
const showExitConfirm = ref(false);

// Para a precisão: todas as lacunas respondidas, inclusive nas tentativas erradas
const gapsAnswered = ref(0);
const gapsCorrect = ref(0);
const xpGained = ref(0);
const levelUp = ref<DisciplineLevelUp | null>(null);

const total = props.challenges.length;
const active = computed(() => queue.value[0] ?? null);
const passedCount = computed(() => Object.keys(passed.value).length);
const progressPercent = computed(() => (passedCount.value / total) * 100);
const accuracy = computed(() => (gapsAnswered.value > 0 ? Math.round((gapsCorrect.value / gapsAnswered.value) * 100) : 100));
const wrongAnswers = computed(() => lastResult.value?.answers.filter((a) => !a.is_correct) ?? []);

// Texto acima do desafio: os blocos anteriores da lei e os da fase que vêm antes dele,
// com as respostas dadas nesta fase.
const earlier = ref<CompletedSegmentData[]>([...props.earlierBlocks]);
const hasMore = ref(props.hasMoreAbove);
const isLoadingMore = ref(false);

const blocksAbove = computed((): CompletedSegmentData[] => {
    if (!active.value) return earlier.value;

    const index = props.phaseBlocks.findIndex((block) => block.uuid === active.value!.uuid);
    const fromPhase = props.phaseBlocks.slice(0, Math.max(0, index)).map((block) => {
        const result = passed.value[block.uuid];
        return result ? { ...block, answers: result.answers, is_completed: true } : block;
    });

    return [...earlier.value, ...fromPhase];
});

const scrollContainerRef = ref<HTMLElement | null>(null);
const sentinelRef = ref<HTMLElement | null>(null);
const activeRef = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;
let resizeObserver: ResizeObserver | null = null;

// Deixa o desafio inteiro à vista, encostado no fim da área de texto (o que já foi lido
// fica acima). Posiciona direto, sem animação: a rolagem suave era interrompida quando o
// rodapé mudava de altura e o desafio novo ficava fora da tela.
function showActive() {
    const container = scrollContainerRef.value;
    const element = activeRef.value;
    if (!container || !element) return;

    const box = container.getBoundingClientRect();
    const rect = element.getBoundingClientRect();
    const delta = rect.height > box.height ? rect.top - box.top : rect.bottom - box.bottom;
    container.scrollTop += delta;
}

async function loadMoreAbove() {
    if (isLoadingMore.value || !hasMore.value || earlier.value.length === 0) return;
    isLoadingMore.value = true;

    const container = scrollContainerRef.value!;
    const oldScrollHeight = container.scrollHeight;

    try {
        const { data } = await axios.get<{ segments: CompletedSegmentData[]; hasMore: boolean }>(route('play.completed', props.legislation.uuid), {
            params: { before_position: earlier.value[0].position, limit: 5 },
        });

        if (data.segments.length > 0) {
            earlier.value = [...data.segments, ...earlier.value];
            hasMore.value = data.hasMore;

            // Mantém na tela o que a pessoa estava lendo
            await nextTick();
            container.scrollTop += container.scrollHeight - oldScrollHeight;
        } else {
            hasMore.value = false;
        }
    } catch {
        // Basta rolar de novo para tentar outra vez
    } finally {
        isLoadingMore.value = false;
    }
}

const { reward: confettiReward } = useReward('beta-confetti', 'confetti', {
    lifetime: 300,
    elementCount: 100,
    spread: 90,
});

async function handleSubmit(data: ChallengeResult) {
    const isPass = data.correct_count >= requiredCorrect(data.total_count);

    gapsAnswered.value += data.total_count;
    gapsCorrect.value += data.correct_count;
    lastResult.value = { ...data, passed: isPass };
    combo.value = null;

    // O resultado aumenta o rodapé; mantém o desafio inteiro à vista
    nextTick(showActive);

    if (isPass) {
        passed.value[data.segment_uuid] = data;
    }

    // Certo ou errado, a energia sai na hora (o progresso continua só no navegador)
    try {
        const response = await axios.post<EnergySpend>(route('beta.play.attempt'), { phase_id: props.phaseId, correct: isPass });
        const spent = response.data;
        if (spent.combo && spent.combo.gained > 0) {
            comboEnergy = spent.energy;
            energy.value = { ...spent.energy, value: spent.energy.value - spent.combo.gained };
        } else {
            setEnergy(spent.energy);
        }
        combo.value = spent.combo;
        outOfEnergy.value = spent.out_of_energy;
    } catch {
        // Falha de rede aqui não deve travar a fase
    }
}

function setEnergy(value: EnergyState) {
    energy.value = value;
    if (authUser.value.energy) authUser.value.energy = value;
}

function comboArrived() {
    if (comboEnergy) setEnergy(comboEnergy);
    comboEnergy = null;
}

function leaveWithoutEnergy() {
    leaving = true;
    router.visit(route('beta.play.energy'));
}

async function next() {
    if (!lastResult.value) return;

    if (outOfEnergy.value) {
        leaveWithoutEnergy();
        return;
    }

    // Errou: o mesmo desafio volta em branco e só passa adiante quando for vencido
    if (lastResult.value.passed) {
        queue.value.shift();
    }

    lastResult.value = null;
    attempt.value++;

    if (queue.value.length === 0) {
        await finish();
        return;
    }

    await nextTick();
    showActive();
}

async function finish() {
    stage.value = 'saving';

    try {
        const response = await axios.post<BetaFinishResponse>(route('beta.play.finish'), {
            phase_id: props.phaseId,
            results: Object.values(passed.value),
        });

        xpGained.value = response.data.xp_gained;
        authUser.value.xp = response.data.user.xp;
        energy.value = response.data.user.energy;
        if (authUser.value.energy) authUser.value.energy = response.data.user.energy;
        stage.value = 'done';

        await nextTick();
        confettiReward();
        playPhaseComplete();
        levelUp.value = response.data.discipline_level_up;
    } catch (error) {
        // 409: a energia acabou no meio e a fase foi perdida
        if (axios.isAxiosError(error) && error.response?.status === 409) {
            leaveWithoutEnergy();
            return;
        }
        stage.value = 'failed-to-save';
    }
}

// Sair no meio perde a fase
let leaving = false;

function exit() {
    if (stage.value === 'playing' && passedCount.value + gapsAnswered.value > 0) {
        showExitConfirm.value = true;
        return;
    }
    backToMap();
}

function backToMap() {
    leaving = true;
    router.visit(route('play.map'));
}

function warnBeforeUnload(event: BeforeUnloadEvent) {
    if (!leaving && stage.value !== 'done' && gapsAnswered.value > 0) {
        event.preventDefault();
    }
}

onMounted(async () => {
    window.addEventListener('beforeunload', warnBeforeUnload);

    // Começa no desafio; só depois observa o topo, senão já carregaria mais ao abrir
    await nextTick();
    showActive();

    // O rodapé (palavras, resultado) muda de altura e encolhe a área de texto
    if (scrollContainerRef.value) {
        resizeObserver = new ResizeObserver(() => showActive());
        resizeObserver.observe(scrollContainerRef.value);
    }

    if (sentinelRef.value && scrollContainerRef.value) {
        observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting) loadMoreAbove();
            },
            { root: scrollContainerRef.value, threshold: 0.1 },
        );
        observer.observe(sentinelRef.value);
    }
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', warnBeforeUnload);
    observer?.disconnect();
    resizeObserver?.disconnect();
});
</script>

<template>
    <Head :title="legislation.title" />

    <div class="flex h-dvh w-full flex-col overflow-hidden bg-white dark:bg-gray-900">
        <span id="beta-confetti" class="pointer-events-none fixed left-1/2 top-1/2 z-[100]"></span>

        <!-- ===== Fim da fase ===== -->
        <PhaseEnd
            v-if="stage === 'done'"
            :title="isReview ? 'Revisão' : 'Fase'"
            :mark="'concluída!'"
            :premium="isSubscriber"
            :count="total"
            count-label="Desafios"
            :xp="xpGained"
            :accuracy="accuracy"
            :level-up="levelUp"
            @done="backToMap"
        />

        <!-- ===== Gravando / erro ao gravar ===== -->
        <div v-else-if="stage !== 'playing'" class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <template v-if="stage === 'saving'">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-blue-500 dark:border-gray-700" />
                <p class="font-medium text-gray-500 dark:text-gray-400">Guardando seu progresso...</p>
            </template>
            <template v-else>
                <p class="text-lg font-bold text-gray-900 dark:text-white">Não foi possível guardar a fase</p>
                <p class="text-gray-500 dark:text-gray-400">Suas respostas continuam aqui. Confira a conexão e tente de novo.</p>
                <GameButton variant="purple" size="lg" @click="finish">Tentar de novo</GameButton>
            </template>
        </div>

        <!-- ===== Jogo ===== -->
        <template v-else>
            <!-- Topo: sair, progresso da fase, energia -->
            <div class="shrink-0 px-3 pt-4 md:px-4">
                <div class="mx-auto flex w-full items-center gap-3 sm:w-[95%] lg:w-[50rem]">
                    <IconButton label="Sair da fase" class="-ml-2 shrink-0" @click="exit">
                        <X class="h-6 w-6" />
                    </IconButton>
                    <div class="h-3 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-green-500 transition-all duration-500" :style="{ width: `${progressPercent}%` }" />
                    </div>
                    <EnergyMeter :energy="energy" />
                    <ComboBurst :combo="combo" @arrive="comboArrived" />
                </div>
                <p class="mx-auto mt-2 w-full truncate text-xs font-medium text-gray-400 dark:text-gray-500 sm:w-[95%] lg:w-[50rem]">
                    {{ legislation.title }} · Passo {{ phaseNumber }} de {{ phasesPerGroup }}
                </p>
            </div>

            <div class="flex min-h-0 flex-1 flex-col">
                <!-- Texto -->
                <div ref="scrollContainerRef" class="min-h-0 flex-1 overflow-y-auto px-3 md:px-4">
                    <div class="mx-auto w-full pt-4 sm:w-[95%] lg:w-[50rem]">
                        <div ref="sentinelRef" class="h-1" />
                        <div v-if="isLoadingMore" class="flex justify-center py-4">
                            <div class="h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-blue-500 dark:border-gray-600" />
                        </div>

                        <CompletedSegment v-for="block in blocksAbove" :key="block.uuid" :segment="block" show-historical />

                        <div v-if="active" ref="activeRef">
                            <ActiveChallenge
                                :key="`${active.uuid}-${attempt}`"
                                :segment="active"
                                controls-target="#beta-controls"
                                hide-result
                                show-historical
                                @submit="handleSubmit"
                            />
                        </div>
                    </div>
                </div>

                <!-- Controles: palavras e Verificar (teleport), depois o resultado -->
                <div v-show="!lastResult" class="shrink-0 border-t-2 border-gray-200 px-3 pb-5 dark:border-gray-800 md:px-4">
                    <div class="mx-auto w-full sm:w-[95%] lg:w-[50rem]">
                        <div id="beta-controls" />
                    </div>
                </div>

                <!-- Resultado: a mesma barra do desafio da etapa -->
                <ResultBar
                    v-if="lastResult"
                    :correct="lastResult.passed"
                    :button-label="outOfEnergy ? 'Sair' : lastResult.passed ? 'Continuar' : 'Tentar de novo'"
                    @continue="next"
                >
                    <p v-if="combo" class="mb-1 flex items-center gap-1 font-bold">
                        Sequência de {{ combo.streak }}!
                        <template v-if="combo.xp > 0"> <Zap class="h-4 w-4 fill-current" /> +{{ combo.xp }} XP </template>
                        <template v-else-if="combo.gained > 0"> <BatteryCharging class="h-4 w-4" /> +{{ combo.gained }} </template>
                    </p>
                    <p v-if="outOfEnergy" class="mb-1 font-bold">Sua energia acabou. Esta fase vai recomeçar.</p>
                    <p v-if="lastResult.answers.length > 0">
                        {{ lastResult.answers.length - wrongAnswers.length }} de {{ lastResult.answers.length }} corretas
                    </p>
                    <template v-if="wrongAnswers.length > 0">
                        <p class="mt-2 font-bold">Resposta correta:</p>
                        <p v-for="answer in wrongAnswers" :key="answer.gap_order" class="mt-1">
                            <span class="line-through opacity-70">{{ answer.user_word }}</span>
                            <span class="mx-1.5">→</span>
                            <span class="font-bold">{{ answer.correct_word }}</span>
                        </p>
                    </template>
                </ResultBar>
            </div>
        </template>

        <!-- Confirmação de saída -->
        <div v-if="showExitConfirm" class="fixed inset-0 z-[150] flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 dark:bg-gray-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Sair da fase?</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    O progresso só é guardado quando você termina os {{ total }} desafios. Se sair agora, vai recomeçar a fase.
                </p>
                <div class="mt-5 flex flex-col gap-3">
                    <GameButton variant="purple" class="w-full" @click="showExitConfirm = false">Continuar jogando</GameButton>
                    <GameButton variant="white" class="w-full" @click="backToMap">Sair mesmo assim</GameButton>
                </div>
            </div>
        </div>
    </div>
</template>
