<script setup lang="ts">
import LevelUpOverlay from '@/components/LevelUpOverlay.vue';
import ResultBar from '@/components/ResultBar.vue';
import GameButton from '@/components/ui/GameButton.vue';
import IconButton from '@/components/ui/IconButton.vue';
import Notice from '@/components/ui/Notice.vue';
import { playPhaseComplete } from '@/lib/sounds';
import type { BetaExamAnswerResponse, BetaExamAttempt, BetaExamGiven, BetaExamResult, EnergyState } from '@/types/beta-play';
import type { DisciplineLevelUp } from '@/types/legislation-play';
import { boardLabel } from '@/types/study-material';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios, { isAxiosError } from 'axios';
import { BatteryCharging, Check, Timer, X, Zap } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref } from 'vue';
import { useReward } from 'vue-rewards';

const props = defineProps<{
    exam: {
        key: string;
        section_number: number;
        legislation_title: string;
        questions: number;
        seconds: number;
        xp_per_correct: number;
        best_correct: number;
        attempts: number;
    };
    // Prova em andamento ao abrir a tela (a pessoa saiu e voltou com o tempo correndo)
    attempt: BetaExamAttempt | null;
}>();

const page = usePage();
const authUser = computed(() => page.props.auth.user as { xp: number; energy?: EnergyState | null });
// A prova rende energia (por acerto novo e combo, creditada na entrega); quem assina não precisa
const showEnergy = computed(() => !authUser.value.energy?.infinite);

// Dia da prova: uma prova com tempo, só com questões que a pessoa já viu nos desafios dos
// quadrados da etapa. Quem manda no tempo é o servidor (ele guarda a hora em que a prova
// acaba); o relógio daqui só mostra. Errar não custa nada, e o XP somado aparece ao lado do
// relógio, mas só é creditado na entrega.
const stage = ref<'intro' | 'playing' | 'result'>('intro');
const running = ref<BetaExamAttempt | null>(null);
const answers = ref<Record<string, BetaExamGiven>>({});
const index = ref(0);
const xp = ref(0);
const energyGained = ref(0);
const result = ref<BetaExamResult | null>(null);
const timedOut = ref(false);

const starting = ref(false);
const startError = ref(false);
const selected = ref<string | null>(null);
const checking = ref(false);
const checkError = ref(false);
const feedback = ref<BetaExamGiven | null>(null);
const confirmingExit = ref(false);
const levelUp = ref<DisciplineLevelUp | null>(null);
const contentRef = ref<HTMLElement | null>(null);

// === Relógio ===
const secondsLeft = ref(0);
let endsAt = 0;
let ticker: ReturnType<typeof setInterval> | undefined;

function stopClock() {
    clearInterval(ticker);
    ticker = undefined;
}

function startClock(seconds: number) {
    stopClock();
    endsAt = Date.now() + seconds * 1000;
    secondsLeft.value = seconds;
    ticker = setInterval(() => {
        secondsLeft.value = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
        if (secondsLeft.value === 0) {
            timedOut.value = true;
            finish();
        }
    }, 250);
}

onUnmounted(stopClock);

function clock(seconds: number): string {
    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

function duration(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return [minutes > 0 ? `${minutes} min` : '', rest > 0 ? `${rest} s` : ''].filter(Boolean).join(' ');
}

// === Prova ===
const questions = computed(() => running.value?.questions ?? []);
const question = computed(() => questions.value[index.value]);
const answeredCount = computed(() => Object.keys(answers.value).length);
const progressPercent = computed(() => (answeredCount.value / Math.max(1, questions.value.length)) * 100);

const choices = computed(() => {
    const current = question.value;
    if (!current) return [];
    return current.type === 'true_false'
        ? [
              { key: 'certo', text: 'Certo' },
              { key: 'errado', text: 'Errado' },
          ]
        : Object.entries(current.options ?? {}).map(([key, text]) => ({ key, text }));
});

function enter(data: BetaExamAttempt) {
    running.value = data;
    answers.value = { ...data.answers };
    xp.value = data.xp;
    energyGained.value = data.energy;
    // Volta na primeira questão ainda sem resposta
    const next = data.questions.findIndex((item) => !(item.uuid in data.answers));
    index.value = next === -1 ? 0 : next;
    selected.value = null;
    feedback.value = null;
    result.value = null;
    timedOut.value = false;
    stage.value = 'playing';
    startClock(data.seconds_left);
}

if (props.attempt) enter(props.attempt);

async function start() {
    if (starting.value) return;
    starting.value = true;
    startError.value = false;
    try {
        const { data } = await axios.post<BetaExamAttempt>(route('beta.play.exam.start', { exam: props.exam.key }));
        enter(data);
    } catch {
        startError.value = true;
    } finally {
        starting.value = false;
    }
}

const { reward: confettiReward } = useReward('exam-confetti', 'confetti', { lifetime: 300, elementCount: 100, spread: 90 });

async function showResult(data: BetaExamResult) {
    stopClock();
    result.value = data;
    xp.value = data.xp_gained;
    energyGained.value = data.energy_gained;
    authUser.value.xp = data.user.xp;
    if (authUser.value.energy) authUser.value.energy = data.user.energy;
    levelUp.value = data.discipline_level_up;
    confirmingExit.value = false;
    stage.value = 'result';
    await nextTick();
    if (data.correct > 0) {
        confettiReward();
        playPhaseComplete();
    }
}

async function check() {
    const current = question.value;
    if (!current || !selected.value || checking.value || stage.value !== 'playing') return;
    checking.value = true;
    checkError.value = false;

    try {
        const { data } = await axios.post<BetaExamAnswerResponse>(route('beta.play.exam.answer', { exam: props.exam.key }), {
            question: current.uuid,
            answer: selected.value,
        });
        const given = { answer: selected.value, is_correct: data.is_correct, correct_answer: data.correct_answer };
        answers.value[current.uuid] = given;
        xp.value = data.xp;
        energyGained.value = data.energy;
        feedback.value = given;

        // Última questão: a prova já foi entregue; o resultado aparece depois do "Continuar"
        if (data.result) {
            stopClock();
            result.value = data.result;
        }
    } catch (error) {
        // O tempo acabou antes de a resposta chegar: o servidor já entregou a prova
        if (isAxiosError(error) && error.response?.status === 409 && error.response.data?.result) {
            timedOut.value = true;
            showResult(error.response.data.result);
        } else {
            checkError.value = true;
        }
    } finally {
        checking.value = false;
    }
}

async function advance() {
    if (result.value) {
        showResult(result.value);
        return;
    }
    index.value++;
    selected.value = null;
    feedback.value = null;
    checkError.value = false;
    await nextTick();
    contentRef.value?.scrollTo({ top: 0 });
}

let finishing = false;

/** Entrega a prova: o tempo acabou ou a pessoa decidiu parar. */
async function finish() {
    if (finishing || stage.value !== 'playing') return;
    finishing = true;
    stopClock();
    try {
        const { data } = await axios.post<BetaExamResult>(route('beta.play.exam.finish', { exam: props.exam.key }));
        showResult(data);
    } catch {
        // Sem conexão: o servidor entrega sozinho quando a pessoa abrir a prova de novo
        backToMap();
    } finally {
        finishing = false;
    }
}

function backToMap() {
    router.visit(route('beta.play.map'));
}

function leave() {
    if (stage.value === 'playing') confirmingExit.value = true;
    else backToMap();
}

function choiceClass(key: string) {
    if (!feedback.value) {
        return selected.value === key ? 'is-selected' : '';
    }
    if (key.toLowerCase() === feedback.value.correct_answer.toLowerCase()) {
        return 'is-right';
    }
    if (key === feedback.value.answer) {
        return 'is-wrong';
    }
    return 'is-dim';
}

const answerLabel = (item: { type: string; options: Record<string, string> | null }, key: string) =>
    item.type === 'true_false'
        ? key.charAt(0).toUpperCase() + key.slice(1).toLowerCase()
        : `${key.toUpperCase()}) ${item.options?.[key.toUpperCase()] ?? ''}`;
</script>

<template>
    <Head title="Dia da prova" />

    <div class="flex h-dvh w-full flex-col overflow-hidden bg-white dark:bg-gray-900">
        <span id="exam-confetti" class="pointer-events-none fixed left-1/2 top-1/2 z-[100]"></span>
        <LevelUpOverlay :level-up="levelUp" @done="levelUp = null" />

        <!-- ===== Antes de começar ===== -->
        <div v-if="stage === 'intro'" class="flex min-h-0 flex-1 flex-col overflow-y-auto px-4 pb-6">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col">
                <div class="flex flex-1 flex-col justify-center py-8">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-purple-500 text-white">
                        <Timer class="h-8 w-8" :stroke-width="2.5" />
                    </span>
                    <h1 class="mt-5 text-3xl font-bold text-gray-900 dark:text-white">Dia da prova</h1>
                    <p class="mt-1 text-base text-gray-500 dark:text-gray-400">Etapa {{ exam.section_number }} · {{ exam.legislation_title }}</p>

                    <dl class="mt-6 divide-y-2 divide-gray-100 rounded-2xl border-2 border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                        <div class="flex items-center justify-between px-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Questões</dt>
                            <dd class="font-bold text-gray-900 dark:text-white">{{ exam.questions }}</dd>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Tempo</dt>
                            <dd class="font-bold text-gray-900 dark:text-white">{{ duration(exam.seconds) }}</dd>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Cada acerto</dt>
                            <dd class="font-bold text-purple-600 dark:text-purple-400">+{{ exam.xp_per_correct }} XP</dd>
                        </div>
                        <div v-if="showEnergy" class="flex items-center justify-between gap-3 px-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Energia</dt>
                            <dd class="flex items-center gap-1 text-right font-bold text-brand-soft-ink dark:text-brand">
                                <BatteryCharging class="h-4 w-4 shrink-0" /> +1 por acerto, +2 a cada 3 seguidos
                            </dd>
                        </div>
                        <div v-if="exam.attempts > 0" class="flex items-center justify-between px-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Seu melhor resultado</dt>
                            <dd class="font-bold text-green-600 dark:text-green-400">{{ exam.best_correct }} de {{ exam.questions }}</dd>
                        </div>
                    </dl>

                    <p class="mt-5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                        São as questões que você já viu nos desafios desta etapa, em outra ordem. Vá o mais longe que conseguir: errar não tira nada,
                        e o tempo não para se você sair da prova.
                    </p>
                    <p v-if="exam.attempts > 0" class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                        Ao refazer, só dão XP as questões que você ainda não tinha acertado.
                    </p>
                </div>

                <Notice v-if="startError" short class="mb-3 justify-center">Não foi possível começar agora. Tente de novo.</Notice>
                <GameButton variant="premium" size="lg" class="w-full" :disabled="starting" @click="start">Começar a prova</GameButton>
                <GameButton variant="white" size="lg" class="mt-3 w-full" @click="backToMap">Voltar ao mapa</GameButton>
            </div>
        </div>

        <!-- ===== Prova ===== -->
        <template v-else-if="stage === 'playing' && question">
            <!-- Topo: sair, progresso, relógio e XP somado -->
            <div class="shrink-0 px-3 pt-4 md:px-4">
                <div class="mx-auto flex w-full items-center gap-3 sm:w-[95%] lg:w-[50rem]">
                    <IconButton label="Sair da prova" class="-ml-2 shrink-0" @click="leave">
                        <X class="h-6 w-6" />
                    </IconButton>
                    <div
                        class="h-3 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                        role="progressbar"
                        :aria-valuenow="answeredCount"
                        aria-valuemin="0"
                        :aria-valuemax="questions.length"
                    >
                        <div class="h-full rounded-full bg-purple-500 transition-all duration-500" :style="{ width: `${progressPercent}%` }" />
                    </div>
                </div>
                <div class="mx-auto mt-3 flex w-full items-center justify-between sm:w-[95%] lg:w-[50rem]">
                    <span
                        class="flex items-center gap-1.5 text-lg font-bold tabular-nums"
                        :class="secondsLeft <= 30 ? 'text-red-500' : 'text-gray-900 dark:text-white'"
                        role="timer"
                        :aria-label="`Tempo restante: ${clock(secondsLeft)}`"
                    >
                        <Timer class="h-5 w-5" :stroke-width="2.5" />
                        {{ clock(secondsLeft) }}
                    </span>
                    <span
                        class="flex items-center gap-1 rounded-lg bg-purple-100 px-2.5 py-1 text-base font-bold tabular-nums text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                        aria-label="XP somado na prova"
                    >
                        <Zap class="h-4 w-4 fill-current" />
                        +{{ xp }} XP
                    </span>
                    <span
                        v-if="showEnergy"
                        class="flex items-center gap-1 rounded-lg bg-brand-soft px-2.5 py-1 text-base font-bold tabular-nums text-brand-soft-ink"
                        aria-label="Energia somada na prova"
                    >
                        <BatteryCharging class="h-4 w-4" />
                        +{{ energyGained }}
                    </span>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col">
                <div ref="contentRef" class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 md:px-4">
                    <div class="mx-auto w-full pb-6 pt-4 sm:w-[95%] lg:w-[50rem]">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-bold text-gray-900 dark:text-white">Questão {{ index + 1 }} de {{ questions.length }}</span>
                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                Estilo {{ boardLabel(question.board) }}
                            </span>
                        </div>

                        <p v-if="question.type === 'true_false'" class="mt-4 text-sm text-gray-500 dark:text-gray-400">Julgue o item a seguir.</p>
                        <p class="mt-3 whitespace-pre-line text-base leading-relaxed text-gray-900 dark:text-gray-100">{{ question.statement }}</p>

                        <div class="mt-5 space-y-2.5">
                            <button
                                v-for="choice in choices"
                                :key="choice.key"
                                type="button"
                                class="mz-choice text-sm"
                                :class="choiceClass(choice.key)"
                                :disabled="!!feedback"
                                :aria-pressed="selected === choice.key"
                                @click="selected = choice.key"
                            >
                                <span v-if="question.type === 'multiple_choice'" class="mz-choice-key">{{ choice.key }}</span>
                                <span class="min-w-0 flex-1" :class="{ 'font-bold': question.type === 'true_false' }">{{ choice.text }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!feedback" class="shrink-0 border-t-2 border-gray-100 px-3 pb-5 pt-4 dark:border-gray-800 md:px-4">
                    <div class="mx-auto w-full sm:w-[95%] lg:w-[50rem]">
                        <Notice v-if="checkError" short class="mb-3">Não foi possível corrigir agora. Tente de novo.</Notice>
                        <GameButton variant="premium" size="lg" class="w-full" :disabled="!selected || checking" @click="check">Responder</GameButton>
                    </div>
                </div>

                <!-- O comentário de cada questão fica para o fim, para não gastar o tempo da prova -->
                <ResultBar v-else :key="question.uuid" :correct="feedback.is_correct" @continue="advance">
                    <p v-if="!feedback.is_correct">
                        Gabarito: <span class="font-bold capitalize">{{ feedback.correct_answer }}</span>
                    </p>
                </ResultBar>
            </div>

            <!-- Sair no meio: o tempo continua correndo, então a escolha é entregar ou seguir -->
            <div
                v-if="confirmingExit"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
            >
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 dark:bg-gray-800">
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Entregar a prova agora?</p>
                    <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                        O tempo não para se você sair. Entregando agora, você recebe o XP dos acertos até aqui.
                    </p>
                    <GameButton variant="premium" size="lg" class="mt-4 w-full" @click="confirmingExit = false">Continuar a prova</GameButton>
                    <GameButton variant="white" size="lg" class="mt-3 w-full" @click="finish">Entregar a prova</GameButton>
                </div>
            </div>
        </template>

        <!-- ===== Resultado ===== -->
        <div v-else-if="stage === 'result' && result" class="min-h-0 flex-1 overflow-y-auto px-4 pb-8">
            <div class="mx-auto w-full max-w-2xl">
                <div class="pt-10 text-center">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ timedOut ? 'Tempo esgotado!' : 'Prova entregue!' }}</h1>
                    <p class="mt-2 text-base text-gray-500 dark:text-gray-400">
                        Você respondeu {{ result.answered }} de {{ result.total }} {{ result.total === 1 ? 'questão' : 'questões' }}.
                    </p>
                </div>

                <div class="mt-6 grid gap-3 text-center" :class="showEnergy ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3'">
                    <div class="rounded-2xl border-2 border-green-200 px-2 py-4 dark:border-green-900">
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ result.correct }}</p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ result.correct === 1 ? 'Acerto' : 'Acertos' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border-2 border-purple-200 px-2 py-4 dark:border-purple-900">
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">+{{ result.xp_gained }}</p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">XP</p>
                    </div>
                    <div v-if="showEnergy" class="rounded-2xl border-2 border-brand-soft px-2 py-4">
                        <p class="flex items-center justify-center gap-1 text-2xl font-bold text-brand-soft-ink dark:text-brand">
                            <BatteryCharging class="h-5 w-5" />+{{ result.energy_gained }}
                        </p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Energia</p>
                    </div>
                    <div class="rounded-2xl border-2 border-gray-200 px-2 py-4 dark:border-gray-700">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ result.best_correct }}</p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Seu melhor</p>
                    </div>
                </div>

                <GameButton variant="green" size="lg" class="mt-6 w-full" @click="backToMap">Voltar ao mapa</GameButton>

                <!-- Gabarito comentado do que foi respondido -->
                <template v-if="result.review.length > 0">
                    <h2 class="mt-10 text-lg font-bold text-gray-900 dark:text-white">Gabarito comentado</h2>
                    <div
                        v-for="(item, i) in result.review"
                        :key="item.uuid"
                        class="mt-4 rounded-2xl border-2 border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-bold"
                            :class="item.is_correct ? 'text-green-600 dark:text-green-400' : 'text-red-500'"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white"
                                :class="item.is_correct ? 'bg-green-500' : 'bg-red-500'"
                            >
                                <Check v-if="item.is_correct" class="h-4 w-4" :stroke-width="3" />
                                <X v-else class="h-4 w-4" :stroke-width="3" />
                            </span>
                            Questão {{ i + 1 }} · Estilo {{ boardLabel(item.board) }}
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-900 dark:text-gray-100">{{ item.statement }}</p>
                        <p v-if="!item.is_correct" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-bold">Sua resposta:</span> {{ answerLabel(item, item.answer) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-bold">Gabarito:</span> {{ answerLabel(item, item.correct_answer) }}
                        </p>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ item.explanation }}</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400"><span class="font-bold">Fundamento:</span> {{ item.legal_basis }}</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
