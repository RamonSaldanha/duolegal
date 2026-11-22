<script setup lang="ts">
import PhaseEnd from '@/components/beta-preview/PhaseEnd.vue';
import EnergyMeter from '@/components/EnergyMeter.vue';
import GuidedReading from '@/components/GuidedReading.vue';
import LevelUpOverlay from '@/components/LevelUpOverlay.vue';
import ResultBar from '@/components/ResultBar.vue';
import GameButton from '@/components/ui/GameButton.vue';
import IconButton from '@/components/ui/IconButton.vue';
import Notice from '@/components/ui/Notice.vue';
import { playPhaseComplete } from '@/lib/sounds';
import type { EnergyState } from '@/types/beta-play';
import type { DisciplineLevelUp } from '@/types/legislation-play';
import {
    boardLabel,
    type CheckpointAnswerResponse,
    type CheckpointMaterial,
    type CheckpointQuestion,
    type CheckpointStepResponse,
} from '@/types/study-material';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { BatteryCharging, X } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useReward } from 'vue-rewards';

const props = defineProps<{
    checkpoint: { key: string; section_number: number; step_number: number; total: number; completed: number };
    materials: CheckpointMaterial[];
    energy: EnergyState;
}>();

const page = usePage();
const authUser = computed(() => page.props.auth.user as { xp: number; energy?: EnergyState | null });

// Cada questão nova gasta energia e 3 certas seguidas devolvem um tanto. Se ela acaba, a
// pessoa sai (o que fez fica guardado) e volta quando tiver energia.
const energy = ref<EnergyState>(props.energy);
const outOfEnergy = ref(false);
// Quem assina pula o convite Premium do fim de fase
const isSubscriber = computed(() => Boolean((page.props.auth.user as { has_infinite_lives?: boolean }).has_infinite_lives));

// O desafio do quadrado é uma sequência de passos: o resumo do material e, logo depois, as
// questões dele (normalmente um material por quadrado). Joga o que ainda não foi feito; se
// tudo já foi, é uma revisão. Cada passo é gravado na hora (o servidor guarda o resumo
// lido e cada resposta): quem sai no meio volta no primeiro passo que falta.
const isReview = props.materials.every((material) => material.done);
const queue = isReview ? props.materials : props.materials.filter((material) => !material.done);

// O que já foi feito de cada material da fila, contando o que veio guardado do servidor
const progress = ref<Record<string, { summary: boolean; answered: number[] }>>(
    Object.fromEntries(
        queue.map((material) => [material.uuid, { summary: !isReview && material.summary_read, answered: isReview ? [] : [...material.answered] }]),
    ),
);

type Step = { kind: 'summary'; material: CheckpointMaterial } | { kind: 'question'; material: CheckpointMaterial; question: CheckpointQuestion };

// Só os passos que faltam
const steps: Step[] = queue.flatMap((material) => {
    const saved = progress.value[material.uuid];
    return [
        ...(saved.summary ? [] : [{ kind: 'summary' as const, material }]),
        ...material.questions
            .filter((question) => !saved.answered.includes(question.position))
            .map((question) => ({ kind: 'question' as const, material, question })),
    ];
});

const stage = ref<'playing' | 'done'>('playing');
const stepIndex = ref(0);
const step = computed(() => steps[stepIndex.value]);

const selected = ref<string | null>(null);
const checking = ref(false);
const checkError = ref(false);
// Gabarito comentado da questão atual: só chega do servidor depois da resposta
const feedback = ref<CheckpointAnswerResponse | null>(null);
// Só o que foi respondido nesta visita, para a tela do fim
const correctCount = ref(0);
const answeredCount = ref(0);

const xpGained = ref(0);
const levelUp = ref<DisciplineLevelUp | null>(null);
// A subida de estrela espera o "Continuar", para não cobrir o comentário da questão
let pendingLevelUp: DisciplineLevelUp | null = null;

// A barra é do material em que a pessoa está: o resumo e as questões dele.
const progressPercent = computed(() => {
    const material = step.value.material;
    const made = progress.value[material.uuid];
    return (((made.summary ? 1 : 0) + made.answered.length) / (1 + material.questions.length)) * 100;
});
const materialNumber = computed(() => props.materials.indexOf(step.value.material) + 1);
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

/** O servidor gravou um passo: soma o XP, se o material foi concluído com ele. */
function applyStep(data: CheckpointStepResponse) {
    xpGained.value += data.xp_gained;
    authUser.value.xp = data.user.xp;
    pendingLevelUp = data.discipline_level_up ?? pendingLevelUp;
}

function confirmSummary() {
    const material = step.value.material;
    summaryConfirmed.value = true;
    progress.value[material.uuid].summary = true;
    if (isReview) return;

    // Se não gravar, a pessoa só relê o resumo caso saia antes de terminar o material
    axios
        .post<CheckpointStepResponse>(route('beta.play.checkpoint.summary', { checkpoint: props.checkpoint.key }), { material: material.uuid })
        .then(({ data }) => applyStep(data))
        .catch(() => {});
}

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
        progress.value[current.material.uuid].answered.push(current.question.position);
        answeredCount.value++;
        if (data.is_correct) correctCount.value++;
        applyStep(data);
        feedback.value = data;
        energy.value = data.energy;
        outOfEnergy.value = data.out_of_energy;
        if (authUser.value.energy) authUser.value.energy = data.energy;

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
    if (outOfEnergy.value) {
        router.visit(route('beta.play.energy'));
        return;
    }
    if (pendingLevelUp) {
        levelUp.value = pendingLevelUp;
        pendingLevelUp = null;
    }
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

// Tudo já foi gravado passo a passo; aqui é só a comemoração
async function finish() {
    stage.value = 'done';
    await nextTick();
    confettiReward();
    playPhaseComplete();
}

// Sair a qualquer momento não perde nada: o que foi feito já está guardado
function backToMap() {
    router.visit(route('beta.play.map'));
}

function choiceClass(key: string) {
    if (!feedback.value) {
        return selected.value === key ? 'is-selected' : '';
    }
    if (key.toLowerCase() === feedback.value.correct_answer.toLowerCase()) {
        return 'is-right';
    }
    if (key === selected.value) {
        return 'is-wrong';
    }
    return 'is-dim';
}
</script>

<template>
    <Head title="Desafio do quadrado" />

    <div class="flex h-dvh w-full flex-col overflow-hidden bg-white dark:bg-gray-900">
        <span id="checkpoint-confetti" class="pointer-events-none fixed left-1/2 top-1/2 z-[100]"></span>
        <LevelUpOverlay :level-up="levelUp" @done="levelUp = null" />

        <!-- ===== Fim da fase ===== -->
        <PhaseEnd
            v-if="stage === 'done'"
            :title="isReview ? 'Revisão' : 'Desafio'"
            :mark="isReview ? 'concluída!' : 'concluído!'"
            :premium="isSubscriber"
            :count="answeredCount"
            :count-label="answeredCount === 1 ? 'Questão' : 'Questões'"
            :xp="xpGained"
            :accuracy="accuracy"
            @done="backToMap"
        />

        <!-- ===== Jogo ===== -->
        <template v-else>
            <!-- Topo: sair e progresso do material -->
            <div class="shrink-0 px-3 pt-4 md:px-4">
                <div class="mx-auto flex w-full items-center gap-3 sm:w-[95%] lg:w-[50rem]">
                    <IconButton label="Voltar ao mapa" class="-ml-2 shrink-0" @click="backToMap">
                        <X class="h-6 w-6" />
                    </IconButton>
                    <div
                        class="h-3 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                        role="progressbar"
                        :aria-valuenow="Math.round(progressPercent)"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <!-- A chave zera a barra de uma vez ao trocar de material, em vez de ela voltar deslizando -->
                        <div
                            :key="step.material.uuid"
                            class="h-full rounded-full bg-green-500 transition-all duration-500"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <EnergyMeter :energy="energy" />
                </div>
                <p class="mx-auto mt-2 w-full truncate text-xs font-medium text-gray-400 dark:text-gray-500 sm:w-[95%] lg:w-[50rem]">
                    {{ step.material.legislation_title }} · Passo {{ checkpoint.step_number }} de {{ checkpoint.step_number }} · Desafio<template
                        v-if="materials.length > 1"
                    >
                        · Material {{ materialNumber }} de {{ materials.length }}</template
                    >
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
                            <GameButton
                                variant="purple"
                                size="lg"
                                class="mt-8 w-full"
                                :disabled="!summaryRead || summaryConfirmed"
                                @click="confirmSummary"
                                >Concluir</GameButton
                            >
                            <p v-if="!summaryRead" class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                <template v-if="summaryProgress > 0">
                                    Leitura em {{ Math.round(summaryProgress * 100) }}%. Rolar ou pular trechos não conta: a leitura guiada precisa
                                    passar pelo texto.
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
                                    class="choice mz-choice select-none text-sm"
                                    :class="[choiceClass(choice.key), { 'is-dragging': drag?.key === choice.key && drag.horizontal }]"
                                    :style="{ translate: `${choiceOffset(choice.key)}px 0` }"
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
                                        class="mz-choice-key"
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
                        <Notice v-if="checkError" short class="mb-3">Não foi possível corrigir agora. Tente de novo.</Notice>
                        <GameButton variant="purple" size="lg" class="w-full" :disabled="!selected || checking" @click="check">Responder</GameButton>
                    </div>
                </div>

                <!-- Resumo concluído: a mesma barra de acerto, antes de seguir -->
                <ResultBar v-if="step.kind === 'summary' && summaryConfirmed" correct title="Resumo concluído!" @continue="advance" />

                <!-- Resultado: a mesma barra das fases de lacunas -->
                <ResultBar
                    v-else-if="step.kind === 'question' && feedback"
                    :correct="feedback.is_correct"
                    :button-label="outOfEnergy ? 'Sair' : undefined"
                    @continue="advance"
                >
                    <p v-if="feedback.combo" class="mb-1 flex items-center gap-1 font-bold">
                        Sequência de {{ feedback.combo.streak }}!
                        <template v-if="feedback.combo.gained > 0"> <BatteryCharging class="h-4 w-4" /> +{{ feedback.combo.gained }} </template>
                    </p>
                    <p v-if="outOfEnergy" class="mb-1 font-bold">Sua energia acabou. O que você fez fica guardado.</p>
                    <p v-if="!feedback.is_correct">
                        Gabarito: <span class="font-bold capitalize">{{ feedback.correct_answer }}</span>
                    </p>
                    <p v-if="feedback.material_completed && feedback.xp_gained > 0" class="font-bold">
                        Material concluído: +{{ feedback.xp_gained }} XP
                    </p>
                </ResultBar>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* Deixa a rolagem vertical com o navegador e o arrasto horizontal com a alternativa. O arrasto
   usa translate, e o afundar do .mz-choice usa transform: um não apaga o outro. */
.choice {
    touch-action: pan-y;
    transition:
        translate 0.2s ease,
        transform 0.08s ease,
        box-shadow 0.08s ease,
        background-color 0.15s ease,
        opacity 0.15s ease;
}
.choice.is-dragging {
    transition:
        transform 0.08s ease,
        box-shadow 0.08s ease;
}
</style>
