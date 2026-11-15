<script setup lang="ts">
import StudySummary from '@/components/StudySummary.vue';
import GameButton from '@/components/ui/GameButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { boardLabel, type BonusAnswerResponse, type BonusFinishResponse, type BonusQuestion } from '@/types/study-material';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, Check, X, Zap } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    material: { uuid: string; title: string };
    legislationTitle: string;
    summaryHtml: string;
    questions: BonusQuestion[];
    alreadyCompleted: boolean;
    xp: { base: number; per_correct: number };
}>();

// Primeiro o resumo, depois as questões uma a uma, e o resultado no fim.
const stage = ref<'summary' | 'questions' | 'saving' | 'failed-to-save' | 'done'>('summary');
const index = ref(0);
const selected = ref<string | null>(null);
const checking = ref(false);
const checkError = ref(false);
// Gabarito comentado da questão atual: só chega do servidor depois da resposta
const feedback = ref<BonusAnswerResponse | null>(null);
const answers = ref<Record<number, string>>({});
const result = ref<BonusFinishResponse | null>(null);

const question = computed(() => props.questions[index.value]);
const isLast = computed(() => index.value === props.questions.length - 1);
const maxXp = computed(() => props.xp.base + props.xp.per_correct * props.questions.length);

const choices = computed(() =>
    question.value.type === 'true_false'
        ? [
              { key: 'certo', text: 'Certo' },
              { key: 'errado', text: 'Errado' },
          ]
        : Object.entries(question.value.options ?? {}).map(([key, text]) => ({ key, text })),
);

function start() {
    index.value = 0;
    selected.value = null;
    feedback.value = null;
    answers.value = {};
    result.value = null;
    stage.value = 'questions';
    window.scrollTo(0, 0);
}

async function check() {
    if (!selected.value || checking.value) return;
    checking.value = true;
    checkError.value = false;

    try {
        const { data } = await axios.post<BonusAnswerResponse>(route('beta.play.bonus.answer', { studyMaterial: props.material.uuid }), {
            position: question.value.position,
            answer: selected.value,
        });
        answers.value[question.value.position] = selected.value;
        feedback.value = data;
    } catch {
        checkError.value = true;
    } finally {
        checking.value = false;
    }
}

function next() {
    if (isLast.value) {
        finish();
        return;
    }
    index.value++;
    selected.value = null;
    feedback.value = null;
    window.scrollTo(0, 0);
}

async function finish() {
    stage.value = 'saving';
    try {
        const { data } = await axios.post<BonusFinishResponse>(route('beta.play.bonus.finish', { studyMaterial: props.material.uuid }), {
            answers: answers.value,
        });
        result.value = data;
        stage.value = 'done';
    } catch {
        stage.value = 'failed-to-save';
    }
}

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
    <Head :title="material.title" />

    <AppLayout>
        <div class="mx-auto max-w-2xl px-4 py-6">
            <Link
                :href="route('beta.play.map')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:underline dark:text-gray-400"
            >
                <ArrowLeft class="h-4 w-4" />
                Voltar ao mapa
            </Link>

            <p class="mt-4 text-xs font-bold uppercase tracking-wide text-purple-600 dark:text-purple-400">Desafio bônus · {{ legislationTitle }}</p>
            <h1 class="mt-1 text-2xl font-bold leading-snug text-gray-900 dark:text-white">{{ material.title }}</h1>

            <!-- ===== Resumo ===== -->
            <template v-if="stage === 'summary'">
                <div class="mt-6">
                    <StudySummary :html="summaryHtml" />
                </div>

                <div class="mt-8 rounded-2xl border-2 border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-bold text-gray-900 dark:text-white">
                        Agora, {{ questions.length === 1 ? '1 questão' : `${questions.length} questões` }} no estilo das bancas
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        <template v-if="alreadyCompleted">Você já concluiu este desafio. Pode refazer à vontade, sem XP.</template>
                        <template v-else>Vale até {{ maxXp }} XP. Não gasta vida.</template>
                    </p>
                    <GameButton variant="purple" size="lg" class="mt-4 w-full" @click="start">Responder as questões</GameButton>
                </div>
            </template>

            <!-- ===== Questões ===== -->
            <template v-else-if="stage === 'questions'">
                <div class="mt-6 flex items-center justify-between text-sm">
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
                        class="flex w-full items-start gap-3 rounded-xl border-2 px-4 py-3 text-left text-sm text-gray-800 dark:text-gray-100"
                        :class="choiceClass(choice.key)"
                        :disabled="!!feedback"
                        :aria-pressed="selected === choice.key"
                        @click="selected = choice.key"
                    >
                        <span v-if="question.type === 'multiple_choice'" class="font-bold">{{ choice.key }}</span>
                        <span class="min-w-0 flex-1" :class="{ 'font-bold': question.type === 'true_false' }">{{ choice.text }}</span>
                    </button>
                </div>

                <!-- Antes da resposta -->
                <template v-if="!feedback">
                    <p v-if="checkError" class="mt-4 text-sm text-red-600">Não foi possível corrigir agora. Tente de novo.</p>
                    <GameButton variant="purple" size="lg" class="mt-6 w-full" :disabled="!selected || checking" @click="check">
                        Responder
                    </GameButton>
                </template>

                <!-- Gabarito comentado -->
                <template v-else>
                    <div
                        class="mt-6 rounded-2xl border-2 p-4"
                        :class="
                            feedback.is_correct
                                ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/40'
                                : 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/40'
                        "
                    >
                        <p
                            class="flex items-center gap-2 font-bold"
                            :class="feedback.is_correct ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'"
                        >
                            <component :is="feedback.is_correct ? Check : X" class="h-5 w-5" :stroke-width="3" />
                            {{ feedback.is_correct ? 'Você acertou' : 'Você errou' }}
                            <span class="font-medium"
                                >· Gabarito: <span class="capitalize">{{ feedback.correct_answer }}</span></span
                            >
                        </p>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ feedback.explanation }}</p>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-bold">Fundamento:</span> {{ feedback.legal_basis }}
                        </p>
                    </div>
                    <GameButton :variant="feedback.is_correct ? 'green' : 'purple'" size="lg" class="mt-6 w-full" @click="next">
                        {{ isLast ? 'Ver o resultado' : 'Próxima questão' }}
                    </GameButton>
                </template>
            </template>

            <!-- ===== Gravando / erro ao gravar ===== -->
            <div v-else-if="stage === 'saving'" class="mt-16 text-center text-gray-500 dark:text-gray-400">Gravando o resultado...</div>
            <div v-else-if="stage === 'failed-to-save'" class="mt-16 text-center">
                <p class="text-gray-700 dark:text-gray-300">Não foi possível gravar o resultado.</p>
                <GameButton variant="purple" size="lg" class="mt-4" @click="finish">Tentar de novo</GameButton>
            </div>

            <!-- ===== Resultado ===== -->
            <template v-else-if="result">
                <div class="mt-8 rounded-2xl border-2 border-gray-200 p-6 text-center dark:border-gray-700">
                    <p class="text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Resultado</p>
                    <p class="mt-2 text-4xl font-bold text-gray-900 dark:text-white">{{ result.correct_count }}/{{ result.total }}</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-300">
                        {{ result.correct_count === 1 ? 'questão certa' : 'questões certas' }}
                    </p>

                    <p
                        v-if="result.xp_gained > 0"
                        class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-purple-100 px-4 py-1.5 font-bold text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                    >
                        <Zap class="h-4 w-4" />
                        +{{ result.xp_gained }} XP
                    </p>
                    <p v-else class="mt-5 text-sm text-gray-500 dark:text-gray-400">
                        Você já tinha concluído este desafio, então desta vez não há XP.
                    </p>
                </div>

                <GameButton variant="purple" size="lg" class="mt-6 w-full" :href="route('beta.play.map')">Voltar ao mapa</GameButton>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <GameButton variant="white" class="w-full" @click="stage = 'summary'">Rever o resumo</GameButton>
                    <GameButton variant="white" class="w-full" @click="start">Refazer as questões</GameButton>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
