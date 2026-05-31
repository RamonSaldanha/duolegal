<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Flame, Trophy, Share2, Check, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface WeekDay {
    date: string;
    weekday: string;
    count: number;
    studied: boolean;
    is_today: boolean;
    is_future: boolean;
}

interface MonthDay {
    date: string;
    day: number;
    count: number;
    studied: boolean;
    is_today: boolean;
    is_future: boolean;
}

interface MonthData {
    year: number;
    month: number;
    label: string;
    first_weekday: number; // 1=Seg .. 7=Dom
    prev: string;
    next: string | null;
    days: MonthDay[];
}

interface Props {
    current_streak: number;
    longest_streak: number;
    played_today: boolean;
    today_count: number;
    week: WeekDay[];
    month: MonthData;
}

const props = defineProps<Props>();

const WEEKDAY_LABELS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

const dayLabel = (n: number) => (n === 1 ? 'dia' : 'dias');

const subtitle = computed(() => {
    if (props.current_streak === 0) return 'Conclua um exercício hoje para começar sua ofensiva!';
    if (!props.played_today) return 'Estude hoje para não perder sua ofensiva!';
    if (props.current_streak >= 30) return 'Você está imparável! 🚀';
    if (props.current_streak >= 7) return 'Uma semana ou mais de foco. Continue assim!';
    return 'Todo dia conta. Vamos juntos!';
});

// Intensidade -> cor sólida (heatmap)
function intensityClass(day: MonthDay): string {
    if (day.count === 0) return 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-600';
    if (day.count === 1) return 'bg-orange-200 text-orange-800';
    if (day.count <= 3) return 'bg-orange-400 text-white';
    return 'bg-orange-600 text-white';
}

// Células vazias antes do dia 1 (offset da grade, semana começando na segunda)
const leadingBlanks = computed(() => Array.from({ length: props.month.first_weekday - 1 }));

function goToMonth(month: string | null) {
    if (!month) return;
    router.get(route('ofensiva.index'), { month }, { preserveScroll: true, preserveState: false });
}

// ===== Compartilhamento (captura o card de resumo como PNG) =====
const cardRef = ref<HTMLElement | null>(null);
const sharing = ref(false);

async function shareCard() {
    if (!cardRef.value || sharing.value) return;
    sharing.value = true;
    try {
        const { toPng } = await import('html-to-image');
        const dataUrl = await toPng(cardRef.value, { pixelRatio: 2, cacheBust: true, backgroundColor: '#ffffff' });
        const blob = await (await fetch(dataUrl)).blob();
        const file = new File([blob], 'ofensiva-memorize.png', { type: 'image/png' });

        const shareData = {
            files: [file],
            title: 'Minha ofensiva no Memorize Direito',
            text: `Estou há ${props.current_streak} ${dayLabel(props.current_streak)} seguidos de estudos no Memorize Direito! 🔥`,
        };

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share(shareData);
        } else {
            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = 'ofensiva-memorize.png';
            link.click();
        }
    } catch (e) {
        if ((e as Error)?.name !== 'AbortError') {
            console.error('Erro ao compartilhar a ofensiva', e);
        }
    } finally {
        sharing.value = false;
    }
}
</script>

<template>
    <Head title="Ofensiva" />

    <AppHeaderLayout :breadcrumbs="[{ title: 'Home', href: '/' }, { title: 'Ofensiva', href: '/ofensiva' }]">
        <div class="mx-auto flex max-w-md flex-col gap-6 px-4 pb-10 pt-6">
            <h1 class="text-center text-xl font-bold tracking-tight text-gray-900 dark:text-white">Ofensiva</h1>

            <!-- Card de resumo (compartilhável) -->
            <div
                ref="cardRef"
                class="flex flex-col items-center gap-5 rounded-3xl border border-orange-100 bg-white px-6 py-7 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="text-center">
                    <p class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                        <span class="text-orange-500">{{ current_streak }} {{ dayLabel(current_streak) }}</span>
                        seguidos de estudos
                    </p>
                    <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
                </div>

                <!-- Régua semanal -->
                <div class="flex w-full justify-between gap-1.5">
                    <div
                        v-for="day in week"
                        :key="day.date"
                        class="flex flex-1 flex-col items-center gap-1.5 rounded-2xl px-1 py-2"
                        :class="day.studied
                            ? 'bg-amber-100 dark:bg-amber-900/30'
                            : (day.is_today ? 'bg-orange-50 ring-2 ring-orange-300 dark:bg-gray-800' : 'bg-gray-100 dark:bg-gray-800')"
                    >
                        <span
                            class="text-xs font-bold"
                            :class="day.studied ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400 dark:text-gray-500'"
                        >{{ day.weekday }}</span>
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full"
                            :class="day.studied
                                ? 'bg-yellow-400'
                                : 'border-2 border-gray-300 dark:border-gray-600'"
                        >
                            <Check v-if="day.studied" class="h-4 w-4 text-white" :stroke-width="3" />
                        </span>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="grid w-full grid-cols-2 gap-3">
                    <div class="flex flex-col items-center gap-1 rounded-2xl bg-orange-50 py-3 dark:bg-gray-800">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sequência atual</span>
                        <span class="flex items-center gap-1.5 text-lg font-extrabold text-orange-500">
                            <Flame class="h-5 w-5" fill="currentColor" />{{ current_streak }} {{ dayLabel(current_streak) }}
                        </span>
                    </div>
                    <div class="flex flex-col items-center gap-1 rounded-2xl bg-orange-50 py-3 dark:bg-gray-800">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Seu recorde</span>
                        <span class="flex items-center gap-1.5 text-lg font-extrabold text-orange-600">
                            <Trophy class="h-5 w-5" />{{ longest_streak }} {{ dayLabel(longest_streak) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Botão compartilhar -->
            <button
                type="button"
                :disabled="sharing"
                @click="shareCard"
                class="mx-auto flex items-center gap-2 rounded-full bg-orange-500 px-6 py-3 font-bold text-white shadow-md transition-colors hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <Share2 class="h-5 w-5" />
                {{ sharing ? 'Gerando imagem...' : 'Compartilhar' }}
            </button>

            <!-- Calendário mensal (heatmap de intensidade) -->
            <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <button
                        type="button"
                        @click="goToMonth(month.prev)"
                        class="rounded-full p-1.5 text-gray-500 transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ month.label }}</span>
                    <button
                        type="button"
                        :disabled="!month.next"
                        @click="goToMonth(month.next)"
                        class="rounded-full p-1.5 text-gray-500 transition-colors hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-800"
                    >
                        <ChevronRight class="h-5 w-5" />
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-1.5">
                    <div
                        v-for="label in WEEKDAY_LABELS"
                        :key="label"
                        class="pb-1 text-center text-[11px] font-semibold text-gray-400"
                    >{{ label }}</div>

                    <div v-for="(_, i) in leadingBlanks" :key="'blank-' + i"></div>

                    <div
                        v-for="day in month.days"
                        :key="day.date"
                        class="flex aspect-square items-center justify-center rounded-lg text-xs font-semibold"
                        :class="[intensityClass(day), day.is_today ? 'ring-2 ring-orange-500' : '', day.is_future ? 'opacity-40' : '']"
                        :title="`${day.count} exercício(s) em ${day.date}`"
                    >
                        {{ day.day }}
                    </div>
                </div>

                <!-- Legenda -->
                <div class="mt-3 flex items-center justify-end gap-1.5 text-[11px] text-gray-400">
                    <span>Menos</span>
                    <span class="h-3 w-3 rounded bg-gray-100 dark:bg-gray-800"></span>
                    <span class="h-3 w-3 rounded bg-orange-200"></span>
                    <span class="h-3 w-3 rounded bg-orange-400"></span>
                    <span class="h-3 w-3 rounded bg-orange-600"></span>
                    <span>Mais</span>
                </div>
            </div>
        </div>
    </AppHeaderLayout>
</template>
