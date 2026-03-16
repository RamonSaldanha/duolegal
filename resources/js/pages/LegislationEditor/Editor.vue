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
import { ref, computed } from 'vue';

const props = defineProps<{
    legislation: Legislation;
    segments: LegislationSegment[];
}>();

const publishing = ref(false);
const autoCreating = ref(false);
const stopAutoCreateFlag = ref(false);
const autoCreateProgress = ref<{ current: number; total: number } | null>(null);
const activeSegmentUuid = ref<string | null>(null);

const hasText = computed(() => (props.legislation.raw_text ?? '').length > 0);

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

        // 2. Cria blocos um a um via Inertia
        for (let i = 0; i < boundaries.length; i++) {
            if (stopAutoCreateFlag.value) break;

            await new Promise<void>((resolve) => {
                router.post(
                    route('editor.create-block', props.legislation.uuid),
                    { char_start: boundaries[i].char_start, char_end: boundaries[i].char_end },
                    {
                        preserveScroll: true,
                        onFinish: () => {
                            autoCreateProgress.value = { current: i + 1, total };
                            resolve();
                        },
                    },
                );
            });

            // Delay entre requests para efeito visual
            if (i < boundaries.length - 1 && !stopAutoCreateFlag.value) {
                await new Promise((r) => setTimeout(r, 300));
            }
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
