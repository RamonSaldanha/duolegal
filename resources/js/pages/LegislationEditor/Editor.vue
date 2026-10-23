<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import EditorHeader from '@/components/legislation-editor/EditorHeader.vue';
import OriginalTextPanel from '@/components/legislation-editor/OriginalTextPanel.vue';
import InteractiveTextPanel from '@/components/legislation-editor/InteractiveTextPanel.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Download, FileText } from 'lucide-vue-next';
import type { Legislation, LegislationSegment } from '@/types/legislation';
import { BIG_BLOCK_WORDS, wordCount } from '@/types/legislation';
import { ref, computed, watch } from 'vue';

const props = defineProps<{
    legislation: Legislation;
    segments: LegislationSegment[];
}>();

// Quantos blocos cada requisição da criação automática cria
const AUTO_CREATE_BATCH = 50;

// Cópia local dos blocos: a criação em lote acrescenta aqui sem recarregar a página.
// Ações via Inertia (remover, lacunas, publicar) trazem a lista nova pelas props.
const segments = ref<LegislationSegment[]>([...props.segments]);
watch(
    () => props.segments,
    (value) => {
        segments.value = [...value];
    },
);

const publishing = ref(false);
const autoCreating = ref(false);
const stopAutoCreateFlag = ref(false);
const autoCreateProgress = ref<{ current: number; total: number } | null>(null);
const activeSegmentUuid = ref<string | null>(null);

const hasText = computed(() => (props.legislation.raw_text ?? '').length > 0);

// Blocos grandes, na ordem do texto, para o atalho "próximo bloco grande" do topo
const bigSegments = computed(() =>
    segments.value
        .filter((s) => wordCount(s.original_text) >= BIG_BLOCK_WORDS)
        .sort((a, b) => a.char_start - b.char_start),
);
const bigBlockIndex = ref<number | null>(null);

function nextBigBlock() {
    if (bigSegments.value.length === 0) return;
    bigBlockIndex.value = bigBlockIndex.value === null ? 0 : (bigBlockIndex.value + 1) % bigSegments.value.length;
    selectSegment(bigSegments.value[bigBlockIndex.value].uuid);
}

const importForm = useForm({
    source_url: props.legislation.source_url ?? '',
});

function importText() {
    importForm.post(route('editor.import-text', props.legislation.uuid), {
        preserveScroll: true,
    });
}

function selectSegment(uuid: string) {
    activeSegmentUuid.value = uuid;
    const el = document.getElementById(`segment-${uuid}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function autoCreate() {
    autoCreating.value = true;
    stopAutoCreateFlag.value = false;
    autoCreateProgress.value = null;

    try {
        // 1. Detecta todas as boundaries (JSON, não Inertia)
        const response = await axios.post(
            route('editor.detect-boundaries', props.legislation.uuid),
        );

        const boundaries = response.data.boundaries;
        const total = boundaries.length;

        if (total === 0) {
            autoCreating.value = false;
            return;
        }

        autoCreateProgress.value = { current: 0, total };

        // 2. Cria os blocos em lotes (JSON), acrescentando só os novos na lista local
        for (let i = 0; i < boundaries.length; i += AUTO_CREATE_BATCH) {
            if (stopAutoCreateFlag.value) break;

            const batch = boundaries
                .slice(i, i + AUTO_CREATE_BATCH)
                .map((b: { char_start: number; char_end: number }) => ({ char_start: b.char_start, char_end: b.char_end }));

            const created = await axios.post<{ segments: LegislationSegment[] }>(
                route('editor.store-blocks', props.legislation.uuid),
                { boundaries: batch },
            );

            segments.value.push(...created.data.segments);
            autoCreateProgress.value = { current: Math.min(i + batch.length, total), total };
        }
    } catch (error) {
        console.error('Auto-create failed:', error);
    } finally {
        autoCreating.value = false;
        autoCreateProgress.value = null;
    }
}

function stopAutoCreate() {
    stopAutoCreateFlag.value = true;
}

function removeBlock(segmentUuid: string) {
    // Remover apaga o progresso de quem já concluiu o bloco: pede confirmação nesse caso
    const completed = segments.value.find((s) => s.uuid === segmentUuid)?.completed_count ?? 0;
    if (
        completed > 0 &&
        !window.confirm(
            `${completed} aluno(s) já concluíram este bloco. Remover apaga esse progresso, e os blocos que forem criados no lugar ` +
                'aparecem como pendentes para eles, travando as fases seguintes até refazerem. Remover mesmo assim?',
        )
    ) {
        return;
    }

    router.delete(
        route('editor.remove-block', segmentUuid),
        { preserveScroll: true },
    );
}

function toggleLacuna(segmentUuid: string, word: string, position: number) {
    router.post(
        route('editor.toggle-lacuna', segmentUuid),
        { word, word_position: position },
        { preserveScroll: true },
    );
}

function removeLacuna(lacunaUuid: string) {
    router.delete(
        route('editor.remove-lacuna', lacunaUuid),
        { preserveScroll: true },
    );
}

function togglePublish() {
    const newStatus = props.legislation.status === 'published' ? 'draft' : 'published';
    publishing.value = true;
    router.put(
        route('editor.update', props.legislation.uuid),
        { title: props.legislation.title, status: newStatus },
        {
            preserveScroll: true,
            onFinish: () => (publishing.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Editor: ${legislation.title}`" />

    <div class="h-screen flex flex-col overflow-hidden bg-white dark:bg-gray-950">
        <EditorHeader
            :legislation="legislation"
            :publishing="publishing"
            :auto-creating="autoCreating"
            :has-text="hasText"
            :auto-create-progress="autoCreateProgress"
            :big-block-count="bigSegments.length"
            :big-block-index="bigBlockIndex"
            @next-big-block="nextBigBlock"
            @auto-create="autoCreate"
            @stop-auto-create="stopAutoCreate"
            @toggle-publish="togglePublish"
        />

        <!-- Sem texto: tela de importação -->
        <main v-if="!hasText" class="flex-1 flex items-center justify-center p-6">
            <Card class="w-full max-w-lg">
                <CardHeader class="text-center">
                    <FileText class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                    <CardTitle>Importar Texto da Legislação</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="importText" class="space-y-4">
                        <div>
                            <Label for="source_url">URL da legislação</Label>
                            <Input
                                id="source_url"
                                v-model="importForm.source_url"
                                placeholder="https://www.planalto.gov.br/ccivil_03/constituicao/constituicaocompilado.htm"
                                class="mt-1"
                            />
                            <p v-if="importForm.errors.source_url" class="text-sm text-red-500 mt-1">
                                {{ importForm.errors.source_url }}
                            </p>
                        </div>
                        <Button type="submit" class="w-full gap-2" :disabled="importForm.processing">
                            <Download class="w-4 h-4" />
                            {{ importForm.processing ? 'Buscando texto...' : 'Buscar e Importar Texto' }}
                        </Button>
                        <p class="text-xs text-gray-400 text-center">
                            O texto será importado e os blocos de desafio serão criados automaticamente.
                        </p>
                    </form>
                </CardContent>
            </Card>
        </main>

        <!-- Com texto: editor dois painéis -->
        <main v-else class="flex-1 flex overflow-hidden">
            <!-- Esquerda: texto original (read-only) -->
            <OriginalTextPanel
                :raw-text="legislation.raw_text"
                :segments="segments"
            />

            <!-- Direita: texto interativo com blocos inline -->
            <InteractiveTextPanel
                :raw-text="legislation.raw_text"
                :segments="segments"
                :active-segment-uuid="activeSegmentUuid"
                @select-segment="selectSegment"
                @remove-block="removeBlock"
                @toggle-lacuna="toggleLacuna"
                @remove-lacuna="removeLacuna"
            />
        </main>
    </div>
</template>
