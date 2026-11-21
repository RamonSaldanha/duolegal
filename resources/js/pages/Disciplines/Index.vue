<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Head } from '@inertiajs/vue3';
import { BookOpen, Star, Trophy } from 'lucide-vue-next';
import StarBadge from '@/components/StarBadge.vue';
import StarRow from '@/components/StarRow.vue';
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
    stars: number;
    next_star_xp: number | null;
    badge_url: string | null;
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
    props.disciplineProgress
        .filter(d => d.stars > 0)
        .sort((a, b) => b.stars - a.stars || b.total_xp - a.total_xp)
);

const inactiveDisciplines = computed(() =>
    props.disciplineProgress.filter(d => d.stars === 0)
);

// xp_for_next_level é o XP que o nível inteiro pede; o que falta é a diferença.
const xpToNextLevel = computed(() => Math.max(0, props.globalLevel.xp_for_next_level - props.globalLevel.current_xp_in_level));

const starsEarned = computed(() => props.disciplineProgress.reduce((sum, d) => sum + d.stars, 0));
const starsPossible = computed(() => props.disciplineProgress.length * 5);

const fmt = (n: number) => n.toLocaleString('pt-BR');

// Mesma medida do texto ao lado: XP total em relação à próxima estrela.
const nextStarPercent = (discipline: DisciplineProgress) =>
    discipline.next_star_xp === null ? 100 : Math.min(100, (discipline.total_xp / discipline.next_star_xp) * 100);
</script>

<template>
    <Head title="Conquistas" />

    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 px-4 pb-8">

            <!-- Nível global: só a medalha, o XP e a barra até o próximo nível -->
            <div class="space-y-4 pt-5">
                <h1 class="text-center text-xl font-bold tracking-tight text-gray-900 dark:text-white">Conquistas</h1>

                <section class="rounded-3xl border-2 border-gray-200 bg-card p-5 dark:border-gray-800" aria-label="Nível global">
                    <div class="flex items-center gap-4">
                        <!-- Medalha do nível: hexágono no amarelo do logo, só com o número -->
                        <div class="relative grid h-14 w-[3.125rem] shrink-0 place-items-center" aria-hidden="true">
                            <svg viewBox="0 0 64 72" class="absolute inset-0 h-full w-full">
                                <path d="M32 3 59 18.5v35L32 69 5 53.5v-35Z" fill="var(--mz-brand-edge)" />
                                <path d="M32 3 59 18.5v31L32 65 5 49.5v-31Z" class="fill-brand" />
                            </svg>
                            <span class="relative -mt-1 text-xl font-bold tabular-nums text-brand-ink">{{ globalLevel.level }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-xl font-bold leading-tight text-gray-900 dark:text-white">Nível {{ globalLevel.level }}</p>
                            <p class="mt-0.5 text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ fmt(totalXp) }} XP no total</p>
                        </div>
                    </div>

                    <div
                        class="mt-4 h-3 overflow-hidden rounded-full bg-secondary"
                        role="progressbar"
                        :aria-valuenow="globalLevel.progress_percent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="`${globalLevel.progress_percent}% do nível ${globalLevel.level}`"
                    >
                        <div
                            class="h-full rounded-full bg-brand transition-[width] duration-700 ease-out"
                            :style="{ width: Math.max(globalLevel.progress_percent, 3) + '%' }"
                        />
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Faltam <span class="font-bold tabular-nums text-gray-900 dark:text-white">{{ fmt(xpToNextLevel) }} XP</span> para o nível {{ globalLevel.level + 1 }}
                    </p>
                </section>
            </div>

            <!-- Active disciplines grid -->
            <div v-if="activeDisciplines.length > 0" class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Em progresso</p>
                    <p class="flex items-center gap-1 text-xs font-bold tabular-nums text-gray-500 dark:text-gray-400">
                        <Star class="h-3.5 w-3.5 text-amber-400" fill="currentColor" />{{ starsEarned }} de {{ starsPossible }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div
                        v-for="discipline in activeDisciplines"
                        :key="discipline.id"
                        class="flex flex-col items-center rounded-3xl border-2 border-gray-200 bg-card p-4 dark:border-gray-800"
                    >
                        <StarBadge
                            :badge-url="discipline.badge_url"
                            :name="discipline.name"
                            :stars="discipline.stars"
                            :icon="discipline.icon"
                            :color="discipline.color"
                        />

                        <p class="mt-3 text-center text-base font-bold leading-tight text-gray-900 dark:text-white">
                            {{ discipline.name }}
                        </p>

                        <!-- A arte nova já mostra as estrelas; a fileira só aparece no badge antigo -->
                        <StarRow v-if="!discipline.badge_url" class="mt-1.5" :stars="discipline.stars" />
                        <span
                            v-if="discipline.next_star_xp === null"
                            class="mt-2 inline-flex items-center gap-1 rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-bold text-brand-soft-ink"
                        >
                            <Trophy class="h-3 w-3" />Completa
                        </span>

                        <!-- Progress bar -->
                        <div class="mt-3 w-full">
                            <div class="h-2.5 overflow-hidden rounded-full bg-secondary shadow-[inset_0_1px_0_var(--mz-press)]">
                                <div
                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                    :style="{ width: Math.max(nextStarPercent(discipline), 3) + '%', backgroundColor: discipline.color }"
                                ></div>
                            </div>
                        </div>

                        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <template v-if="discipline.next_star_xp !== null">
                                Faltam {{ fmt(discipline.next_star_xp - discipline.total_xp) }} XP para a {{ discipline.stars + 1 }}ª estrela
                            </template>
                            <template v-else>{{ fmt(discipline.total_xp) }} XP</template>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Inactive disciplines grid -->
            <div v-if="inactiveDisciplines.length > 0" class="space-y-3">
                <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider px-1">Para desbloquear</p>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div
                        v-for="discipline in inactiveDisciplines"
                        :key="discipline.id"
                        class="flex flex-col items-center rounded-3xl border-2 border-dashed border-gray-200 p-4 opacity-60 dark:border-gray-800"
                    >
                        <StarBadge
                            :badge-url="discipline.badge_url"
                            :name="discipline.name"
                            :stars="0"
                            :icon="discipline.icon"
                            :color="discipline.color"
                        />

                        <p class="mt-3 text-center text-base font-medium leading-tight text-gray-400 dark:text-gray-500">
                            {{ discipline.name }}
                        </p>

                        <p class="mt-2 text-center text-xs font-medium text-gray-400 dark:text-gray-500">
                            Conclua um bloco para ganhar a 1ª estrela
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
