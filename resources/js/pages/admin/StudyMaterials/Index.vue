<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { StudyMaterialRow } from '@/types/study-material';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    legislations: { uuid: string; title: string; materials: number }[];
    selected: { uuid: string; title: string } | null;
    progress: { total_blocks: number; pending_blocks: number } | null;
    materials: StudyMaterialRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Material de estudo', href: '/admin/study-materials' },
];

const flash = computed(() => (usePage().props as any).flash ?? {});

type Filter = 'all' | 'draft' | 'published' | 'skipped' | 'stale';
const filter = ref<Filter>('all');

const matches = (material: StudyMaterialRow, value: Filter) => {
    if (value === 'all') return true;
    if (value === 'stale') return material.is_stale;
    return material.status === value;
};

const filters = computed(() =>
    (
        [
            ['all', 'Todos'],
            ['draft', 'Rascunho'],
            ['published', 'Publicado'],
            ['skipped', 'Pulado'],
            ['stale', 'Desatualizado'],
        ] as [Filter, string][]
    ).map(([value, label]) => ({ value, label, count: props.materials.filter((material) => matches(material, value)).length })),
);

const shown = computed(() => props.materials.filter((material) => matches(material, filter.value)));

const donePercent = computed(() => {
    if (!props.progress || props.progress.total_blocks === 0) return 0;
    return Math.round(((props.progress.total_blocks - props.progress.pending_blocks) / props.progress.total_blocks) * 100);
});

const STATUS_LABEL: Record<string, string> = { draft: 'Rascunho', published: 'Publicado', skipped: 'Pulado' };
const STATUS_CLASS: Record<string, string> = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    published: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    skipped: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

function changeLegislation(event: Event) {
    router.get(route('admin.study-materials.index'), { legislation: (event.target as HTMLSelectElement).value }, { preserveState: false });
}

function rangeLabel(material: StudyMaterialRow) {
    if (!material.first_label || !material.last_label) return 'Trecho incompleto';
    return material.first_label === material.last_label ? material.first_label : `${material.first_label} a ${material.last_label}`;
}

function destroy(material: StudyMaterialRow) {
    const what = material.status === 'skipped' ? 'este trecho pulado' : `o material "${material.title}"`;
    if (confirm(`Apagar ${what}?\n\nO trecho volta a ser pendente e o agente pode gerar de novo.`)) {
        router.delete(route('admin.study-materials.destroy', material.uuid), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Material de estudo" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container space-y-6 p-4 md:p-8">
            <div
                v-if="flash.success"
                class="rounded-lg bg-green-100 px-4 py-3 text-sm font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300"
            >
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="rounded-lg bg-red-100 px-4 py-3 text-sm font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">
                {{ flash.error }}
            </div>

            <Card>
                <CardHeader class="flex flex-col gap-4 space-y-0 md:flex-row md:items-start md:justify-between">
                    <div>
                        <CardTitle class="text-2xl font-bold">Material de estudo</CardTitle>
                        <CardDescription>
                            Resumos e questões gravados pelos agentes de IA. Todo material entra como rascunho e só aparece no mapa beta depois de
                            publicado.
                        </CardDescription>
                    </div>
                    <Link :href="route('admin.study-materials.install')">
                        <Button variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Instalar a skill
                        </Button>
                    </Link>
                </CardHeader>

                <CardContent class="space-y-6">
                    <p v-if="legislations.length === 0" class="py-8 text-center text-muted-foreground">Nenhuma legislação cadastrada.</p>

                    <template v-else>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium">
                                Lei
                                <select
                                    class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    :value="selected?.uuid"
                                    @change="changeLegislation"
                                >
                                    <option v-for="legislation in legislations" :key="legislation.uuid" :value="legislation.uuid">
                                        {{ legislation.title }}{{ legislation.materials ? ` (${legislation.materials})` : '' }}
                                    </option>
                                </select>
                            </label>

                            <div v-if="progress" class="text-sm">
                                <p class="font-medium">Progresso da lei</p>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-green-500" :style="{ width: `${donePercent}%` }" />
                                </div>
                                <p class="mt-1 text-muted-foreground">
                                    {{ progress.total_blocks - progress.pending_blocks }} de {{ progress.total_blocks }} dispositivos em algum trecho
                                    ({{ donePercent }}%). Faltam {{ progress.pending_blocks }}.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="item in filters"
                                :key="item.value"
                                type="button"
                                class="rounded-full border px-3 py-1 text-sm font-medium"
                                :class="
                                    filter === item.value
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800'
                                "
                                @click="filter = item.value"
                            >
                                {{ item.label }} ({{ item.count }})
                            </button>
                        </div>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Dispositivos</TableHead>
                                    <TableHead>Material</TableHead>
                                    <TableHead>Situação</TableHead>
                                    <TableHead>Quadrado</TableHead>
                                    <TableHead class="text-right">Ações</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="material in shown" :key="material.uuid">
                                    <TableCell class="whitespace-nowrap align-top">
                                        <p class="font-medium">{{ rangeLabel(material) }}</p>
                                        <p v-if="material.range_blocks" class="text-xs text-muted-foreground">
                                            {{ material.range_blocks }} no trecho<template v-if="material.status !== 'skipped'"
                                                >, {{ material.covered_blocks }} cobertos</template
                                            >
                                        </p>
                                    </TableCell>
                                    <TableCell class="align-top">
                                        <template v-if="material.status === 'skipped'">
                                            <p class="text-muted-foreground">Trecho pulado</p>
                                            <p class="text-sm">{{ material.skip_reason }}</p>
                                        </template>
                                        <template v-else>
                                            <Link :href="route('admin.study-materials.edit', material.uuid)" class="font-medium hover:underline">
                                                {{ material.title }}
                                            </Link>
                                            <p class="text-xs text-muted-foreground">{{ material.questions_count }} questões</p>
                                        </template>
                                    </TableCell>
                                    <TableCell class="align-top">
                                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-bold" :class="STATUS_CLASS[material.status]">
                                            {{ STATUS_LABEL[material.status] }}
                                        </span>
                                        <span
                                            v-if="material.is_stale"
                                            class="ml-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-800 dark:bg-red-900/40 dark:text-red-300"
                                        >
                                            Desatualizado
                                        </span>
                                    </TableCell>
                                    <TableCell class="align-top text-muted-foreground">
                                        {{ material.status !== 'skipped' && material.square ? material.square : '-' }}
                                    </TableCell>
                                    <TableCell class="text-right align-top">
                                        <div class="flex items-center justify-end gap-1">
                                            <Link v-if="material.status !== 'skipped'" :href="route('admin.study-materials.edit', material.uuid)">
                                                <Button variant="ghost" size="icon">
                                                    <Pencil class="h-4 w-4" />
                                                    <span class="sr-only">Revisar</span>
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                @click="destroy(material)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                <span class="sr-only">Apagar</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="shown.length === 0">
                                    <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                        {{
                                            materials.length === 0
                                                ? 'Esta lei ainda não tem material. Peça ao agente para gerar.'
                                                : 'Nada neste filtro.'
                                        }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </template>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
