<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Head } from '@inertiajs/vue3';
import { BookOpen, Zap } from 'lucide-vue-next';
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
                <div class="text-center">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Conquistas</h1>
                </div>

                <!-- Global stats -->
                <div class="flex items-center gap-4 bg-white dark:bg-gray-800/80 rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="relative flex-shrink-0">
                        <svg class="w-16 h-16 -rotate-90" viewBox="0 0 64 64">
                            <circle cx="32" cy="32" r="28" fill="none" stroke="currentColor" stroke-width="4" class="text-gray-100 dark:text-gray-700" />
                            <circle cx="32" cy="32" r="28" fill="none" stroke="#22C55E" stroke-width="4" stroke-linecap="round"
                                :stroke-dasharray="175.93"
                                :stroke-dashoffset="175.93 - (175.93 * globalLevel.progress_percent / 100)"
                            />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-black text-gray-900 dark:text-white">{{ globalLevel.level }}</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">Nivel Global</p>
                        <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            <Zap class="h-3 w-3 text-yellow-500" />
                            {{ totalXp.toLocaleString() }} XP acumulados
                        </div>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">
                            {{ globalLevel.current_xp_in_level }} / {{ globalLevel.xp_for_next_level }} XP para o nivel {{ globalLevel.level + 1 }}
                        </p>
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
                        class="flex flex-col items-center p-4 pb-3 rounded-2xl bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-700/50 transition-all hover:shadow-sm"
                    >
                        <DisciplineBadge
                            :icon="discipline.icon"
                            :color="discipline.color"
                            :level="discipline.level"
                        />

                        <p class="text-sm font-bold text-gray-900 dark:text-white text-center leading-tight mt-3">
                            {{ discipline.name }}
                        </p>

                        <!-- Progress bar -->
                        <div class="w-full mt-2">
                            <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                    :style="{ width: Math.max(discipline.progress_percent, 3) + '%', backgroundColor: discipline.color }"
                                ></div>
                            </div>
                        </div>

                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">
                            {{ discipline.current_xp_in_level }} XP / {{ discipline.xp_for_next_level }} XP
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
                        class="flex flex-col items-center p-4 pb-3 rounded-2xl bg-gray-50 dark:bg-gray-800/40 border border-dashed border-gray-200 dark:border-gray-700 opacity-60"
                    >
                        <DisciplineBadge
                            :icon="discipline.icon"
                            :color="discipline.color"
                            :level="0"
                            :locked="true"
                        />

                        <p class="text-sm font-medium text-gray-400 dark:text-gray-500 text-center leading-tight mt-3">
                            {{ discipline.name }}
                        </p>

                        <div class="w-full mt-2">
                            <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden"></div>
                        </div>

                        <p class="text-[11px] text-gray-300 dark:text-gray-600 mt-1">
                            0 XP / {{ discipline.xp_for_next_level }} XP
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
