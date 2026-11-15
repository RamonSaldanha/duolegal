<script setup lang="ts">
import GuidedReading from '@/components/GuidedReading.vue';
import LevelUpOverlay from '@/components/LevelUpOverlay.vue';
import PhaseComplete from '@/components/PhaseComplete.vue';
import ResultBar from '@/components/ResultBar.vue';
import GameButton from '@/components/ui/GameButton.vue';
import type { DisciplineLevelUp } from '@/types/legislation-play';
import {
    boardLabel,
    type CheckpointAnswerResponse,
    type CheckpointFinishResponse,
    type CheckpointMaterial,
    type CheckpointQuestion,
} from '@/types/study-material';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useReward } from 'vue-rewards';

const props = defineProps<{
    checkpoint: { key: string; number: number; total: number; completed: number };
    materials: CheckpointMaterial[];
}>();

const page = usePage();
const authUser = computed(() => page.props.auth.user as { xp: number });

// A fase é uma sequência de passos: o resumo de cada material e, logo depois, as questões
// dele. Joga os materiais ainda não feitos nesta etapa; se todos já foram, é uma revisão de
// tudo. Como na fase normal, nada é gravado antes do fim: o XP vem só ao terminar.
const isReview = props.materials.every((material) => material.done);
const queue = isReview ? props.materials : props.materials.filter((material) => !material.done);

type Step = { kind: 'summary'; material: CheckpointMaterial } | { kind: 'question'; material: CheckpointMaterial; question: CheckpointQuestion };

const steps: Step[] = queue.flatMap((material) => [
    { kind: 'summary' as const, material },
    ...material.questions.map((question) => ({ kind: 'question' as const, material, question })),
]);
const totalQuestions = steps.filter((step) => step.kind === 'question').length;

const stage = ref<'playing' | 'saving' | 'failed-to-save' | 'done'>('playing');
const stepIndex = ref(0);
const step = computed(() => steps[stepIndex.value]);

const selected = ref<string | null>(null);
const checking = ref(false);
const checkError = ref(false);
// Gabarito comentado da questão atual: só chega do servidor depois da resposta
const feedback = ref<CheckpointAnswerResponse | null>(null);
// Respostas por material (uuid) e posição da questão
const answers = ref<Record<string, Record<number, string>>>({});
const correctCount = ref(0);
const answeredCount = ref(0);

const xpGained = ref(0);
const levelUp = ref<DisciplineLevelUp | null>(null);
const showExitConfirm = ref(false);

// O passo atual conta como feito quando o resumo é deixado para trás ou a questão é respondida
const progressPercent = computed(() => {
    if (stage.value !== 'playing') return 100;
    return ((stepIndex.value + (feedback.value || summaryConfirmed.value ? 1 : 0)) / Math.max(1, steps.length)) * 100;
});
const accuracy = computed(() => (answeredCount.value > 0 ? Math.round((correctCount.value / answeredCount.value) * 100) : 100));

const questionNumber = computed(() => {
    const current = step.value;
    if (current.kind !== 'question') return 0;
    return current.material.questions.indexOf(current.question) + 1;
});

const choices = computed(() => {
    const current = step.value;
    if (current.kind !== 'question') return [];
    return current.question.type === 'true_false'
        ? [
              { key: 'certo', text: 'Certo' },
              { key: 'errado', text: 'Errado' },
          ]
        : Object.entries(current.question.options ?? {}).map(([key, text]) => ({ key, text }));
});

const contentRef = ref<HTMLElement | null>(null);

// Arrastar uma alternativa para a direita risca (ou desrisca) ela, para eliminar opções
// como se faz na prova. Arrastar na vertical continua rolando a tela.
const STRIKE_DISTANCE = 48;
const struck = ref<Record<string, boolean>>({});
const drag = ref<{ key: string; startX: number; startY: number; dx: number; horizontal: boolean | null } | null>(null);
let ignoreNextClick = false;

function onChoicePointerDown(event: PointerEvent, key: string) {
    if (feedback.value || (event.pointerType === 'mouse' && event.button !== 0)) return;
    drag.value = { key, startX: event.clientX, startY: event.clientY, dx: 0, horizontal: null };
}

function onChoicePointerMove(event: PointerEvent) {
    const current = drag.value;
    if (!current) return;

    const dx = event.clientX - current.startX;
    const dy = event.clientY - current.startY;

    if (current.horizontal === null && Math.max(Math.abs(dx), Math.abs(dy)) > 8) {
        current.horizontal = Math.abs(dx) > Math.abs(dy);
        if (current.horizontal) (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    }
    if (current.horizontal) current.dx = Math.max(0, Math.min(dx, STRIKE_DISTANCE + 24));
}

function onChoicePointerUp() {
    const current = drag.value;
    drag.value = null;
    if (!current?.horizontal) return;

    // Foi um arrasto, não um toque: não marca a alternativa
    ignoreNextClick = true;
    if (current.dx < STRIKE_DISTANCE) return;

    struck.value[current.key] = !struck.value[current.key];
    if (struck.value[current.key] && selected.value === current.key) selected.value = null;
}

function onChoiceClick(key: string) {
    if (ignoreNextClick) {
        ignoreNextClick = false;
        return;
    }
    selected.value = key;
    struck.value[key] = false;
}

function choiceOffset(key: string) {
    return drag.value?.key === key && drag.value.horizontal ? drag.value.dx : 0;
}

// O "Concluir" do resumo só libera quando a leitura guiada chega à última palavra
const summaryRead = ref(false);
// Quanto do resumo a leitura guiada já percorreu (0 a 1)
const summaryProgress = ref(0);
// Depois do "Concluir", a barra de resultado aparece antes de seguir para as questões
const summaryConfirmed = ref(false);

async function goTo(index: number) {
    stepIndex.value = index;
    summaryRead.value = false;
    summaryProgress.value = 0;
    summaryConfirmed.value = false;
    selected.value = null;
    struck.value = {};
    drag.value = null;
    feedback.value = null;
    checkError.value = false;
    await nextTick();
    contentRef.value?.scrollTo({ top: 0 });
}

async function check() {
    const current = step.value;
    if (current.kind !== 'question' || !selected.value || checking.value) return;
    checking.value = true;
    checkError.value = false;

    try {
        const { data } = await axios.post<CheckpointAnswerResponse>(route('beta.play.checkpoint.answer', { checkpoint: props.checkpoint.key }), {
            material: current.material.uuid,
            position: current.question.position,
            answer: selected.value,
        });
        (answers.value[current.material.uuid] ??= {})[current.question.position] = selected.value;
        answeredCount.value++;
        if (data.is_correct) correctCount.value++;
        feedback.value = data;

        // O comentário aparece embaixo das alternativas
        await nextTick();
        contentRef.value?.scrollTo({ top: contentRef.value.scrollHeight, behavior: 'smooth' });
    } catch {
        checkError.value = true;
    } finally {
        checking.value = false;
    }
}

function advance() {
    if (stepIndex.value < steps.length - 1) {
        goTo(stepIndex.value + 1);
        return;
    }
    finish();
}

const { reward: confettiReward } = useReward('checkpoint-confetti', 'confetti', {
    lifetime: 300,
    elementCount: 100,
    spread: 90,
});

function playSuccessAudio() {
    try {
        const audio = new Audio('/bell-win.wav');
        audio.volume = 0.7;
        audio.play().catch(() => {});
    } catch {
        // Sem som não atrapalha
    }
}

async function finish() {
    stage.value = 'saving';

    try {
        const { data } = await axios.post<CheckpointFinishResponse>(route('beta.play.checkpoint.finish', { checkpoint: props.checkpoint.key }), {
            results: queue.map((material) => ({ material: material.uuid, answers: answers.value[material.uuid] ?? {} })),
        });

        xpGained.value = data.xp_gained;
        authUser.value.xp = data.user.xp;
        stage.value = 'done';

        await nextTick();
        confettiReward();
        playSuccessAudio();
        levelUp.value = data.discipline_level_up;
    } catch {
        stage.value = 'failed-to-save';
    }
}

// Sair no meio perde a fase, como nas fases normais
let leaving = false;
const started = computed(() => stepIndex.value > 0 || feedback.value !== null);

function exit() {
    if (stage.value === 'playing' && started.value) {
        showExitConfirm.value = true;
        return;
    }
    backToMap();
}

function backToMap() {
    leaving = true;
    router.visit(route('beta.play.map'));
}

function warnBeforeUnload(event: BeforeUnloadEvent) {
    if (!leaving && stage.value !== 'done' && started.value) {
        event.preventDefault();
    }
}

onMounted(async () => {
    window.addEventListener('beforeunload', warnBeforeUnload);
    await nextTick();
});
onUnmounted(() => window.removeEventListener('beforeunload', warnBeforeUnload));

function choiceClass(key: string) {
    if (!feedback.value) {
        return selected.value === key
            ? 'border-purple-500 bg-purple-50 dark:bg-purple-950/40'
            : 'border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800';
    }
    if (key.toLowerCase() === feedback.value.correct_answer.toLowerCase()) {
        return 'border-green-500 bg-green-50 dark:bg-green-950/40';
    }
    if (key === selected.value) {
        return 'border-red-400 bg-red-50 dark:bg-red-950/40';
    }
    return 'border-gray-200 opacity-70 dark:border-gray-700';
}
</script>

<template>
    <Head :title="`Desafio da etapa ${checkpoint.number}`" />

    <div class="flex h-dvh w-full flex-col overflow-hidden bg-white dark:bg-gray-900">
        <span id="checkpoint-confetti" class="pointer-events-none fixed left-1/2 top-1/2 z-[100]"></span>
        <LevelUpOverlay :level-up="levelUp" @done="levelUp = null" />

        <!-- ===== Fim da fase ===== -->
        <PhaseComplete
            v-if="stage === 'done'"
            :title="isReview ? 'Revisão concluída!' : 'Fase concluída!'"
            :count="totalQuestions"
            :count-label="totalQuestions === 1 ? 'Questão' : 'Questões'"
            :xp="xpGained"
            :accuracy="accuracy"
            @continue="backToMap"
        />

        <!-- ===== Gravando / erro ao gravar ===== -->
        <div v-else-if="stage !== 'playing'" class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <template v-if="stage === 'saving'">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-purple-500 dark:border-gray-700" />
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
            <!-- Topo: sair e progresso da fase -->
            <div class="shrink-0 px-3 pt-4 md:px-4">
                <div class="mx-auto flex w-full items-center gap-3 sm:w-[95%] lg:w-[50rem]">
                    <button
                        type="button"
                        class="-ml-1 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        aria-label="Sair da fase"
                        @click="exit"
                    >
                        <X class="h-6 w-6" />
                    </button>
                    <div
                        class="h-3 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                        role="progressbar"
                        :aria-valuenow="Math.round(progressPercent)"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div class="h-full rounded-full bg-green-500 transition-all duration-500" :style="{ width: `${progressPercent}%` }" />
                    </div>
                </div>
                <p class="mx-auto mt-2 w-full truncate text-xs font-medium text-gray-400 dark:text-gray-500 sm:w-[95%] lg:w-[50rem]">
                    Desafio da etapa {{ checkpoint.number }} · {{ step.material.legislation_title }}
                </p>
            </div>

            <div class="flex min-h-0 flex-1 flex-col">
                <!-- Conteúdo do passo -->
                <div ref="contentRef" class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 md:px-4">
                    <div class="mx-auto w-full pb-6 pt-4 sm:w-[95%] lg:w-[50rem]">
                        <h1 class="text-2xl font-bold leading-snug text-gray-900 dark:text-white">{{ step.material.title }}</h1>

                        <!-- Resumo -->
                        <div v-if="step.kind === 'summary'" class="mt-5">
                            <GuidedReading
                                :key="step.material.uuid"
                                :html="step.material.summary_html"
                                :scroller="contentRef"
                                @finished="summaryRead = true"
                                @progress="summaryProgress = $event"
                            />
                            <!-- O botão fica no fim do texto e só libera quando a leitura guiada percorreu o resumo -->
                            <GameButton variant="purple" size="lg" class="mt-8 w-full" :disabled="!summaryRead || summaryConfirmed" @click="summaryConfirmed = true">Concluir</GameButton>
                            <p v-if="!summaryRead" class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                <template v-if="summaryProgress > 0">
                                    Leitura em {{ Math.round(summaryProgress * 100) }}%. Rolar ou pular trechos não conta: a leitura guiada precisa passar
                                    pelo texto.
                                </template>
                                <template v-else>Toque em iniciar e acompanhe a leitura guiada até o fim para concluir.</template>
                            </p>
                        </div>

                        <!-- Questão -->
                        <template v-else>
                            <div class="mt-5 flex items-center justify-between text-sm">
                                <span class="font-bold text-gray-900 dark:text-white"
                                    >Questão {{ questionNumber }} de {{ step.material.questions.length }}</span
                                >
                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    Estilo {{ boardLabel(step.question.board) }}
                                </span>
                            </div>

                            <p v-if="step.question.type === 'true_false'" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                Julgue o item a seguir.
                            </p>
                            <p class="mt-3 whitespace-pre-line text-base leading-relaxed text-gray-900 dark:text-gray-100">
                                {{ step.question.statement }}
                            </p>

                            <div class="mt-5 space-y-2.5">
                                <button
                                    v-for="choice in choices"
                                    :key="choice.key"
                                    type="button"
                                    class="choice flex w-full select-none items-start gap-3 rounded-xl border-2 px-4 py-3 text-left text-sm text-gray-800 dark:text-gray-100"
                                    :class="[choiceClass(choice.key), { 'is-dragging': drag?.key === choice.key && drag.horizontal }]"
                                    :style="{ transform: `translateX(${choiceOffset(choice.key)}px)` }"
                                    :disabled="!!feedback"
                                    :aria-pressed="selected === choice.key"
                                    @pointerdown="onChoicePointerDown($event, choice.key)"
                                    @pointermove="onChoicePointerMove"
                                    @pointerup="onChoicePointerUp"
                                    @pointercancel="drag = null"
                                    @click="onChoiceClick(choice.key)"
                                >
                                    <span
                                        v-if="step.question.type === 'multiple_choice'"
                                        class="font-bold"
                                        :class="{ 'line-through opacity-50': struck[choice.key] }"
                                        >{{ choice.key }}</span
                                    >
                                    <span
                                        class="min-w-0 flex-1"
                                        :class="{
                                            'font-bold': step.question.type === 'true_false',
                                            'line-through decoration-2 opacity-50': struck[choice.key],
                                        }"
                                        >{{ choice.text }}</span
                                    >
                                </button>
                            </div>
                            <p v-if="!feedback" class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                                Arraste uma alternativa para a direita para riscá-la.
                            </p>

                            <!-- Comentário do gabarito; o aviso de acerto ou erro fica na barra de baixo -->
                            <div v-if="feedback" class="mt-6 rounded-2xl border-2 border-gray-200 p-4 dark:border-gray-700">
                                <p class="font-bold text-gray-900 dark:text-white">Comentário</p>
                                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                                    {{ feedback.explanation }}
                                </p>
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-bold">Fundamento:</span> {{ feedback.legal_basis }}
                                </p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Botão do passo -->
                <div
                    v-if="step.kind === 'question' && !feedback"
                    class="shrink-0 border-t-2 border-gray-100 px-3 pb-5 pt-4 dark:border-gray-800 md:px-4"
                >
                    <div class="mx-auto w-full sm:w-[95%] lg:w-[50rem]">
                        <p v-if="checkError" class="mb-3 text-sm text-red-600">Não foi possível corrigir agora. Tente de novo.</p>
                        <GameButton variant="purple" size="lg" class="w-full" :disabled="!selected || checking" @click="check"
                            >Responder</GameButton
                        >
                    </div>
                </div>

                <!-- Resumo concluído: a mesma barra de acerto, antes de seguir -->
                <ResultBar v-if="step.kind === 'summary' && summaryConfirmed" correct title="Resumo concluído!" @continue="advance" />

                <!-- Resultado: a mesma barra das fases de lacunas -->
                <ResultBar v-else-if="step.kind === 'question' && feedback" :correct="feedback.is_correct" @continue="advance">
                    <p v-if="!feedback.is_correct">
                        Gabarito: <span class="font-bold capitalize">{{ feedback.correct_answer }}</span>
                    </p>
                </ResultBar>
            </div>
        </template>

        <!-- Confirmação de saída -->
        <div v-if="showExitConfirm" class="fixed inset-0 z-[150] flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 dark:bg-gray-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Sair da fase?</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    O progresso e o XP só são guardados quando você termina a fase. Se sair agora, vai recomeçar.
                </p>
                <div class="mt-5 flex flex-col gap-3">
                    <GameButton variant="purple" class="w-full" @click="showExitConfirm = false">Continuar jogando</GameButton>
                    <GameButton variant="white" class="w-full" @click="backToMap">Sair mesmo assim</GameButton>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Deixa a rolagem vertical com o navegador e o arrasto horizontal com a alternativa */
.choice {
    touch-action: pan-y;
    transition: transform 0.2s ease;
}
.choice.is-dragging {
    transition: none;
}
</style>
