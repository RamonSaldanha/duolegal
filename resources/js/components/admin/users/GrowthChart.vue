<script setup lang="ts">
import { BarElement, CategoryScale, Chart as ChartJS, LinearScale, Tooltip, type ChartOptions } from 'chart.js';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Bar } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

/**
 * Colunas de uma série só (novos cadastros ou novos pagantes por dia).
 *
 * O canvas não lê variável CSS, então as cores vêm em hex e trocam com o tema.
 * Era por isso que o gráfico antigo saía preto no modo escuro.
 */
interface Point {
    date: string;
    label: string;
    value: number;
}

const props = defineProps<{
    points: Point[];
    color: string;
    /** Rótulo do valor no tooltip: [singular, plural]. */
    unit: [string, string];
}>();

const isDark = ref(false);
let observer: MutationObserver | null = null;

onMounted(() => {
    const update = () => (isDark.value = document.documentElement.classList.contains('dark'));
    update();
    observer = new MutationObserver(update);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => observer?.disconnect());

const WEEKDAYS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

const data = computed(() => ({
    labels: props.points.map((p) => p.label),
    datasets: [
        {
            data: props.points.map((p) => p.value),
            backgroundColor: props.color,
            hoverBackgroundColor: props.color,
            borderRadius: { topLeft: 4, topRight: 4 },
            borderSkipped: 'bottom' as const,
            maxBarThickness: 24,
            categoryPercentage: 0.85,
            barPercentage: 0.9,
        },
    ],
}));

const options = computed<ChartOptions<'bar'>>(() => {
    const ink = isDark.value ? '#a3a3a3' : '#737373';
    const grid = isDark.value ? '#262626' : '#ededed';

    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 300 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: isDark.value ? '#f5f5f5' : '#171717',
                titleColor: isDark.value ? '#171717' : '#fafafa',
                bodyColor: isDark.value ? '#171717' : '#fafafa',
                padding: 10,
                cornerRadius: 8,
                displayColors: false,
                titleFont: { weight: 'bold' },
                callbacks: {
                    title: (items) => {
                        const point = props.points[items[0].dataIndex];
                        const weekday = WEEKDAYS[new Date(`${point.date}T12:00:00`).getDay()];
                        return `${point.label} (${weekday})`;
                    },
                    label: (item) => {
                        const value = Number(item.parsed.y ?? 0);
                        return `${value} ${value === 1 ? props.unit[0] : props.unit[1]}`;
                    },
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { color: grid },
                ticks: { color: ink, maxRotation: 0, autoSkip: true, maxTicksLimit: 8, font: { size: 11 } },
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: grid },
                ticks: { color: ink, precision: 0, maxTicksLimit: 5, font: { size: 11 } },
            },
        },
    };
});
</script>

<template>
    <div class="h-48 w-full">
        <Bar :data="data" :options="options" />
    </div>
</template>
