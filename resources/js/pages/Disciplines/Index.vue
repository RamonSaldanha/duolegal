<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Head } from '@inertiajs/vue3';
import { BookOpen, TrendingUp } from 'lucide-vue-next';
import DisciplineBadge from '@/components/DisciplineBadge.vue';
import { computed } from 'vue';

interface DisciplineProgress {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description?: string;
    icon: string;
    color: string;
    total_xp: number;
    level: number;
    current_xp_in_level: number;
    xp_for_next_level: number;
    progress_percent: number;
}

interface LevelInfo {
    level: number;
    current_xp_in_level: number;
    xp_for_next_level: number;
    progress_percent: number;
    total_xp: number;
}

interface Props {
    disciplineProgress: DisciplineProgress[];
    totalXp: number;
    globalLevel: LevelInfo;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Home', href: '/' },
    { title: 'Conquistas', href: '/disciplines' },
];

const activeDisciplines = computed(() =>
    props.disciplineProgress.filter(d => d.total_xp > 0)
);

const inactiveDisciplines = computed(() =>
    props.disciplineProgress.filter(d => d.total_xp === 0)
);
</script>

<template>
    <Head title="Conquistas" />

    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 px-4 pb-8">

            <!-- Header with global level -->
            <div class="pt-5 space-y-4">
                <h1 class="text-center text-xl font-bold text-gray-900 dark:text-white tracking-tight">Conquistas</h1>

                <!-- Global stats -->
                <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 dark:border-transparent dark:bg-gray-900">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-purple-600 text-white">
                            <span class="text-xl font-bold">{{ globalLevel.level }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="mb-1.5 flex items-end justify-between gap-2">
                                <p class="text-lg font-bold text-gray-900 dark:text-white">Nível Global</p>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    {{ globalLevel.progress_percent }}% concluído
                                </span>
                            </div>
                            <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-full rounded-full bg-green-600 transition-all duration-700 ease-out"
                                    :style="{ width: Math.max(globalLevel.progress_percent, 2) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                        <div class="flex flex-col">
                            <span class="mb-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">XP acumulado</span>
                            <p class="text-base font-bold text-gray-900 dark:text-white">{{ totalXp.toLocaleString() }}</p>
                        </div>
                        <div class="flex flex-col items-end">
                            <div class="mb-0.5 flex items-center gap-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Para o nível {{ globalLevel.level + 1 }}</span>
                                <TrendingUp class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                            </div>
                            <p class="text-base font-bold text-gray-900 dark:text-white">
                                {{ globalLevel.xp_for_next_level.toLocaleString() }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">XP</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active disciplines grid -->
            <div v-if="activeDisciplines.length > 0" class="space-y-3">
                <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider px-1">Em progresso</p>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div
                        v-for="discipline in activeDisciplines"
                        :key="discipline.id"
                        class="flex flex-col items-center rounded-2xl border border-gray-200 bg-white p-4 transition-all dark:border-transparent dark:bg-gray-900"
                    >
                        <DisciplineBadge
                            :icon="discipline.icon"
                            :color="discipline.color"
                            :level="discipline.level"
                        />

                        <p class="mt-3 text-center text-base font-bold leading-tight text-gray-900 dark:text-white">
                            {{ discipline.name }}
                        </p>

                        <!-- Progress bar -->
                        <div class="mt-3 w-full">
                            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                    :style="{ width: Math.max(discipline.progress_percent, 3) + '%', backgroundColor: discipline.color }"
                                ></div>
                            </div>
                        </div>

                        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ discipline.current_xp_in_level }} / {{ discipline.xp_for_next_level }} XP
                        </p>
                    </div>
                </div>
            </div>

            <!-- Inactive disciplines grid -->
            <div v-if="inactiveDisciplines.length > 0" class="space-y-3">
                <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider px-1">Para desbloquear</p>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div
                        v-for="discipline in inactiveDisciplines"
                        :key="discipline.id"
                        class="flex flex-col items-center rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-4 opacity-60 dark:border-transparent dark:bg-gray-900"
                    >
                        <DisciplineBadge
                            :icon="discipline.icon"
                            :color="discipline.color"
                            :level="0"
                            :locked="true"
                        />

                        <p class="mt-3 text-center text-base font-medium leading-tight text-gray-400 dark:text-gray-500">
                            {{ discipline.name }}
                        </p>

                        <div class="mt-3 w-full">
                            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        </div>

                        <p class="mt-2 text-xs font-medium text-gray-300 dark:text-gray-600">
                            0 / {{ discipline.xp_for_next_level }} XP
                        </p>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="disciplineProgress.length === 0" class="text-center py-16">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                    <BookOpen class="h-8 w-8 text-gray-400" />
                </div>
                <h3 class="text-base font-bold text-gray-600 dark:text-gray-400 mb-1">
                    Nenhuma conquista disponivel
                </h3>
                <p class="text-sm text-gray-500 max-w-[260px] mx-auto">
                    As conquistas serao criadas pelo administrador. Comece a estudar para ver seu progresso!
                </p>
            </div>
        </div>
    </AppHeaderLayout>
</template>
