<script setup lang="ts">
import StudySummary from '@/components/StudySummary.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { STUDY_BOARDS, STUDY_OPTION_KEYS, type StudyBoard, type StudyQuestionForm, type StudyStatus } from '@/types/study-material';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    legislation: { uuid: string; title: string };
    material: {
        uuid: string;
        title: string;
        summary: string;
        status: StudyStatus;
        is_stale: boolean;
        generated_by_model: string | null;
        skill_version: string | null;
        published_at: string | null;
        block_ids: number[];
        questions: StudyQuestionForm[];
    };
    summaryHtml: string;
    blocks: { id: number; marker: string | null; text: string; covered: boolean }[];
    limits: { min_questions: number; max_questions: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Material de estudo', href: `/admin/study-materials?legislation=${props.legislation.uuid}` },
    { title: 'Revisar', href: '#' },
];

const flash = computed(() => (usePage().props as any).flash ?? {});

const form = useForm({
    title: props.material.title,
    summary: props.material.summary,
    block_ids: [...props.material.block_ids],
    questions: props.material.questions.map((question) => ({ ...question, options: question.options ? { ...question.options } : null })),
});

const error = (key: string) => (form.errors as Record<string, string>)[key];
const questionErrors = (index: number) =>
    Object.entries(form.errors as Record<string, string>)
        .filter(([key]) => key.startsWith(`questions.${index}.`))
        .map(([, message]) => message);

// === Resumo: pré-visualização convertida no servidor, a mesma da tela do jogador ===
const previewHtml = ref(props.summaryHtml);
let previewTimer: ReturnType<typeof setTimeout> | undefined;
let previewRequest = 0;

watch(
    () => form.summary,
    (summary) => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(async () => {
            const request = ++previewRequest;
            try {
                const { data } = await axios.post<{ html: string }>(route('admin.study-materials.preview'), { summary });
                if (request === previewRequest) previewHtml.value = data.html;
            } catch {
                // A pré-visualização antiga continua na tela; o erro aparece ao salvar
            }
        }, 400);
    },
);

// === Questões ===
const emptyOptions = () => Object.fromEntries(STUDY_OPTION_KEYS.map((key) => [key, '']));

function changeBoard(question: StudyQuestionForm, board: StudyBoard) {
    question.board = board;
    const type = board === 'cebraspe' ? 'true_false' : 'multiple_choice';
    if (question.type === type) return;

    // Cebraspe é certo/errado; as outras bancas têm 5 alternativas
    question.type = type;
    question.options = type === 'multiple_choice' ? emptyOptions() : null;
    question.correct_answer = type === 'multiple_choice' ? 'A' : 'certo';
}

function addQuestion() {
    form.questions.push({
        board: 'fgv',
        type: 'multiple_choice',
        statement: '',
        options: emptyOptions(),
        correct_answer: 'A',
        explanation: '',
        legal_basis: '',
    });
}

function removeQuestion(index: number) {
    form.questions.splice(index, 1);
}

function moveQuestion(index: number, step: -1 | 1) {
    const target = index + step;
    if (target < 0 || target >= form.questions.length) return;
    const [question] = form.questions.splice(index, 1);
    form.questions.splice(target, 0, question);
}

// === Dispositivos cobertos ===
function toggleBlock(id: number) {
    const at = form.block_ids.indexOf(id);
    if (at === -1) form.block_ids.push(id);
    else form.block_ids.splice(at, 1);
}

// === Ações ===
function save() {
    form.put(route('admin.study-materials.update', props.material.uuid), { preserveScroll: true });
}

function act(action: 'publish' | 'unpublish' | 'confirm') {
    if (form.isDirty) {
        alert('Há alterações sem salvar. Salve antes.');
        return;
    }
    router.post(route(`admin.study-materials.${action}`, props.material.uuid), {}, { preserveScroll: true });
}

function destroy() {
    if (confirm('Apagar este material?\n\nO trecho volta a ser pendente e o agente pode gerar de novo.')) {
        router.delete(route('admin.study-materials.destroy', props.material.uuid));
    }
}
</script>

<template>
    <Head :title="`Revisar: ${material.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4 md:p-8">
            <div
                v-if="flash.success"
                class="rounded-lg bg-green-100 px-4 py-3 text-sm font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300"
            >
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="rounded-lg bg-red-100 px-4 py-3 text-sm font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">
                {{ flash.error }}
            </div>

            <div
                v-if="material.is_stale"
                class="flex flex-col gap-3 rounded-lg border-2 border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200 md:flex-row md:items-center md:justify-between"
            >
                <p>
                    <strong>Desatualizado.</strong> O texto de algum dispositivo coberto mudou, ou um deles deixou de existir, depois que este
                    material foi gerado. Ele não aparece no mapa. Confira o resumo e os gabaritos contra o texto ao lado e confirme, ou apague para o
                    agente gerar de novo.
                </p>
                <Button type="button" variant="outline" class="shrink-0" @click="act('confirm')">Revisei e confirmo</Button>
            </div>

            <!-- Cabeçalho com as ações -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">{{ legislation.title }}</p>
                    <p class="text-sm">
                        <span
                            class="inline-block rounded-full px-2 py-0.5 text-xs font-bold"
                            :class="
                                material.status === 'published'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                            "
                        >
                            {{ material.status === 'published' ? 'Publicado' : 'Rascunho' }}
                        </span>
                        <span class="ml-2 text-muted-foreground">
                            Modelo: {{ material.generated_by_model || 'não informado' }} · Skill: {{ material.skill_version || 'não informada' }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" :disabled="form.processing" @click="save">Salvar</Button>
                    <Button v-if="material.status === 'draft'" type="button" variant="outline" :disabled="material.is_stale" @click="act('publish')">
                        Publicar
                    </Button>
                    <Button v-else type="button" variant="outline" @click="act('unpublish')">Despublicar</Button>
                    <Button type="button" variant="ghost" class="text-destructive hover:bg-destructive/10 hover:text-destructive" @click="destroy">
                        Apagar
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="min-w-0 space-y-6">
                    <!-- Título e resumo -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Resumo</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label for="title">Título</Label>
                                <Input id="title" v-model="form.title" class="mt-1" />
                                <p v-if="error('title')" class="mt-1 text-sm text-red-600">{{ error('title') }}</p>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div>
                                    <Label for="summary">Markdown</Label>
                                    <Textarea id="summary" v-model="form.summary" class="mt-1 min-h-[28rem] font-mono text-sm" />
                                    <p v-if="error('summary')" class="mt-1 text-sm text-red-600">{{ error('summary') }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Como o aluno vê</p>
                                    <div class="mt-1 min-h-[28rem] rounded-md border border-input p-4">
                                        <StudySummary :html="previewHtml" />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Questões -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0">
                            <CardTitle>Questões ({{ form.questions.length }})</CardTitle>
                            <Button type="button" variant="outline" :disabled="form.questions.length >= limits.max_questions" @click="addQuestion">
                                <Plus class="mr-2 h-4 w-4" />
                                Incluir questão
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <p v-if="error('questions')" class="text-sm text-red-600">{{ error('questions') }}</p>

                            <div
                                v-for="(question, qi) in form.questions"
                                :key="qi"
                                class="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-bold">Questão {{ qi + 1 }}</p>
                                    <div class="flex items-center gap-1">
                                        <Button type="button" variant="ghost" size="icon" :disabled="qi === 0" @click="moveQuestion(qi, -1)">
                                            <ArrowUp class="h-4 w-4" />
                                            <span class="sr-only">Subir</span>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="qi === form.questions.length - 1"
                                            @click="moveQuestion(qi, 1)"
                                        >
                                            <ArrowDown class="h-4 w-4" />
                                            <span class="sr-only">Descer</span>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            :disabled="form.questions.length <= limits.min_questions"
                                            @click="removeQuestion(qi)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            <span class="sr-only">Remover</span>
                                        </Button>
                                    </div>
                                </div>

                                <ul v-if="questionErrors(qi).length" class="list-disc space-y-1 pl-5 text-sm text-red-600">
                                    <li v-for="message in questionErrors(qi)" :key="message">{{ message }}</li>
                                </ul>

                                <label class="block text-sm font-medium">
                                    Banca
                                    <select
                                        class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm md:w-64"
                                        :value="question.board"
                                        @change="changeBoard(question, ($event.target as HTMLSelectElement).value as StudyBoard)"
                                    >
                                        <option v-for="board in STUDY_BOARDS" :key="board.value" :value="board.value">
                                            {{ board.label }} ({{ board.value === 'cebraspe' ? 'certo/errado' : '5 alternativas' }})
                                        </option>
                                    </select>
                                </label>

                                <div>
                                    <Label :for="`statement-${qi}`">Enunciado</Label>
                                    <Textarea :id="`statement-${qi}`" v-model="question.statement" class="mt-1 min-h-28" />
                                </div>

                                <!-- Certo/errado -->
                                <fieldset v-if="question.type === 'true_false'">
                                    <legend class="text-sm font-medium">Gabarito</legend>
                                    <div class="mt-1 flex gap-4">
                                        <label v-for="value in ['certo', 'errado']" :key="value" class="flex items-center gap-2 text-sm capitalize">
                                            <input v-model="question.correct_answer" type="radio" :name="`answer-${qi}`" :value="value" />
                                            {{ value }}
                                        </label>
                                    </div>
                                </fieldset>

                                <!-- Múltipla escolha -->
                                <fieldset v-else-if="question.options" class="space-y-2">
                                    <legend class="text-sm font-medium">Alternativas (marque a correta)</legend>
                                    <div v-for="key in STUDY_OPTION_KEYS" :key="key" class="flex items-start gap-2">
                                        <label class="flex items-center gap-1.5 pt-2 text-sm font-bold">
                                            <input v-model="question.correct_answer" type="radio" :name="`answer-${qi}`" :value="key" />
                                            {{ key }}
                                        </label>
                                        <Textarea v-model="question.options[key]" class="min-h-10" rows="1" />
                                    </div>
                                </fieldset>

                                <div>
                                    <Label :for="`explanation-${qi}`">Comentário</Label>
                                    <Textarea :id="`explanation-${qi}`" v-model="question.explanation" class="mt-1 min-h-24" />
                                </div>

                                <div>
                                    <Label :for="`basis-${qi}`">Fundamento (dispositivo que sustenta o gabarito)</Label>
                                    <Input :id="`basis-${qi}`" v-model="question.legal_basis" class="mt-1" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex items-center gap-3">
                        <Button type="button" :disabled="form.processing" @click="save">Salvar</Button>
                        <Link :href="`/admin/study-materials?legislation=${legislation.uuid}`" class="text-sm text-muted-foreground hover:underline">
                            Voltar à lista
                        </Link>
                    </div>
                </div>

                <!-- Dispositivos do trecho, para conferir o gabarito sem sair da tela -->
                <aside class="xl:sticky xl:top-4 xl:max-h-[calc(100vh-2rem)] xl:self-start xl:overflow-y-auto">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Dispositivos do trecho</CardTitle>
                            <p class="text-sm text-muted-foreground">
                                {{ form.block_ids.length }} de {{ blocks.length }} cobertos. Desmarque os que o material não cobre (revogados,
                                vetados, remissivos).
                            </p>
                            <p v-if="error('block_ids')" class="text-sm text-red-600">{{ error('block_ids') }}</p>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <label
                                v-for="block in blocks"
                                :key="block.id"
                                class="flex cursor-pointer items-start gap-2 rounded-md border p-2 text-sm"
                                :class="
                                    form.block_ids.includes(block.id)
                                        ? 'border-gray-200 dark:border-gray-700'
                                        : 'border-dashed border-gray-300 text-muted-foreground dark:border-gray-600'
                                "
                            >
                                <input type="checkbox" class="mt-1" :checked="form.block_ids.includes(block.id)" @change="toggleBlock(block.id)" />
                                <span class="whitespace-pre-line">{{ block.text }}</span>
                            </label>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
