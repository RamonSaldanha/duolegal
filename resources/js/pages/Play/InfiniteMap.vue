<script setup lang="ts">
// resources\js\pages\Play\InfiniteMap.vue
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Book, FileText, Bookmark, CheckCircle, Star, Repeat } from 'lucide-vue-next';
import DebugPanel from './DebugPanel.vue';

interface User {
    lives: number;
    has_infinite_lives?: boolean;
    id?: number;
    name?: string;
    email?: string;
    xp?: number;
}

interface MapMeta {
    current_phase_id: number | null;
    current_phase_index: number;
    total_phases: number;
    loaded_phases: number;
    has_more: boolean;
    next_offset: number;
}

interface Progress {
    completed: number;
    total: number;
    percentage: number;
    is_fully_complete?: boolean;
    all_attempted?: boolean;
    has_errors?: boolean;
    article_status?: Array<'correct' | 'incorrect' | 'pending'>;
    errors_count?: number;
    is_complete?: boolean;
    needs_review?: boolean;
    articles_to_review_count?: number;
}

interface Phase {
    id: number;
    title: string;
    reference_name: string;
    reference_uuid: string;
    article_count: number;
    difficulty: number;
    first_article: string | null;
    phase_number: number;
    progress: Progress;
    is_blocked: boolean;
    is_current: boolean;
    is_review?: boolean;
    is_complete?: boolean;
    chunk_index?: number;
}

interface OptimizedReferenceGroup {
    reference_name: string;
    reference_uuid: string;
    phase_ids: number[];
}

interface OptimizedModule {
    id: number;
    title: string;
    references: OptimizedReferenceGroup[];
}

const props = defineProps<{
    phases: Phase[];
    modules?: OptimizedModule[];
    meta: MapMeta;
    user: User;
}>();

// Acesso à página atual para verificar se é admin
const page = usePage<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            is_admin: boolean;
            lives?: number;
            avatar?: string;
            has_infinite_lives?: boolean;
        } | null;
    };
}>();

const isAdmin = computed(() => page.props.auth.user?.is_admin);

// Estado do scroll infinito
const loadedPhases = ref<Phase[]>([...props.phases]);
const loadedModules = ref<OptimizedModule[]>(props.modules ? [...props.modules] : []);
const isLoadingMore = ref(false);
const hasMore = ref(props.meta.has_more);
const nextOffset = ref(props.meta.next_offset);

// Sentinel para Intersection Observer
const sentinelRef = ref<HTMLElement | null>(null);

// Flag para alternar entre visualização tradicional e por módulos
const showModuleView = ref(true);

// Configurações dos conectores
const windowWidth = ref(window.innerWidth);

// Função para atualizar a largura da janela
const updateWindowWidth = () => {
    windowWidth.value = window.innerWidth;
};

// Verificar se há múltiplas leis (para mostrar opção de módulos)
const hasMultipleLaws = computed(() => {
    const uniqueRefs = new Set(loadedPhases.value.map(p => p.reference_uuid));
    return uniqueRefs.size > 1;
});

// Decidir se usar módulos ou visualização tradicional
const shouldUseModules = computed(() => {
    return showModuleView.value && hasMultipleLaws.value && loadedModules.value.length > 0;
});

// Função para carregar mais fases
const loadMorePhases = async () => {
    if (isLoadingMore.value || !hasMore.value) return;

    isLoadingMore.value = true;

    try {
        const response = await fetch(
            route('play.map.load-more') + `?offset=${nextOffset.value}&limit=9`
        );

        const data = await response.json();

        // Adicionar novas fases ao array existente
        loadedPhases.value = [...loadedPhases.value, ...data.phases];

        // Reorganizar módulos com todas as fases carregadas
        loadedModules.value = reorganizeModules(loadedPhases.value);

        // Atualizar meta
        hasMore.value = data.has_more;
        nextOffset.value = data.next_offset;
    } catch (error) {
        console.error('Erro ao carregar mais fases:', error);
    } finally {
        isLoadingMore.value = false;
    }
};

// Função para reorganizar módulos após adicionar fases
const reorganizeModules = (allPhases: Phase[]): OptimizedModule[] => {
    // Se apenas uma lei, não mostrar módulos
    const uniqueRefs = [...new Set(allPhases.map(p => p.reference_uuid))];
    if (uniqueRefs.length <= 1) return [];

    // Aproximação: ~16 fases por módulo (2 leis × 8 fases)
    const APPROX_PHASES_PER_MODULE = 16;

    const modules: OptimizedModule[] = [];
    let currentModuleId = 1;

    for (let i = 0; i < allPhases.length; i += APPROX_PHASES_PER_MODULE) {
        const modulePhases = allPhases.slice(i, i + APPROX_PHASES_PER_MODULE);

        // Agrupar por referência dentro do módulo
        const refGroups: Record<string, OptimizedReferenceGroup> = {};

        modulePhases.forEach(phase => {
            if (!refGroups[phase.reference_uuid]) {
                refGroups[phase.reference_uuid] = {
                    reference_name: phase.reference_name,
                    reference_uuid: phase.reference_uuid,
                    phase_ids: []
                };
            }
            refGroups[phase.reference_uuid].phase_ids.push(phase.id);
        });

        modules.push({
            id: currentModuleId++,
            title: `Módulo ${currentModuleId - 1}`,
            references: Object.values(refGroups)
        });
    }

    return modules;
};

// Função para recuperar fases pelos IDs (para módulos otimizados)
const getPhasesByIds = (phaseIds: number[]): Phase[] => {
    const phaseMap = new Map(loadedPhases.value.map(phase => [phase.id, phase]));
    return phaseIds.map(id => phaseMap.get(id)).filter(Boolean) as Phase[];
};

// Expandir módulos otimizados com dados completos das fases
const expandedModules = computed(() => {
    if (!shouldUseModules.value || !loadedModules.value) return [];

    return loadedModules.value.map(module => ({
        ...module,
        references: module.references.map(ref => ({
            reference_name: ref.reference_name,
            reference_uuid: ref.reference_uuid,
            phases: getPhasesByIds(ref.phase_ids)
        }))
    }));
});

// Agrupar fases por referência legal (para visualização tradicional)
const phasesByReference = computed(() => {
    const grouped: Record<string, { name: string; phases: Phase[] }> = {};

    loadedPhases.value.forEach(phase => {
        if (!grouped[phase.reference_uuid]) {
            grouped[phase.reference_uuid] = {
                name: phase.reference_name,
                phases: []
            };
        }
        grouped[phase.reference_uuid].phases.push(phase);
    });

    return grouped;
});

// Converter o objeto de fases em um array para poder acessar o índice
const referenceGroups = computed(() => {
    return Object.entries(phasesByReference.value).map(([uuid, data]) => ({
        uuid,
        name: data.name,
        phases: data.phases
    }));
});

const getPhaseIcon = (phaseNumber: number) => {
    const icons = [Book, FileText, Bookmark, Star, CheckCircle];
    return icons[(phaseNumber - 1) % icons.length];
};

const isPhaseComplete = (phase: Phase): boolean => {
    if (phase.is_review) {
        return phase.progress?.is_complete || false;
    }

    if (phase.progress?.is_fully_complete !== undefined) {
        return phase.progress.is_fully_complete;
    }

    if (phase.is_complete !== undefined) {
        return phase.is_complete;
    }

    if (phase.progress?.completed !== undefined && phase.progress?.total !== undefined) {
        return phase.progress.completed === phase.progress.total && phase.progress.total > 0;
    }

    return false;
};

const getArticleStatus = (phase: Phase): Array<'correct' | 'incorrect' | 'pending'> => {
    if (phase.is_review) {
        return [];
    }

    const total = phase.article_count || phase.progress?.total || 0;
    const completed = phase.progress?.completed || 0;
    const hasErrors = phase.progress?.has_errors || false;

    const statuses = phase.progress?.article_status;
    if (statuses && statuses.length > 0) {
        const trimmed = statuses.slice(0, total);
        return trimmed.length < total
            ? [...trimmed, ...Array(total - trimmed.length).fill('pending')]
            : trimmed;
    }

    if (phase.is_complete && phase.progress?.is_fully_complete) {
        return Array(total).fill('correct');
    }

    if (completed > 0) {
        const result: Array<'correct' | 'incorrect' | 'pending'> = Array(total).fill('pending');

        if (hasErrors) {
            const errorsCountRaw = phase.progress?.errors_count;
            const errorsCount = Math.max(1, Math.min(completed, errorsCountRaw ?? 1));
            const correctCount = completed - errorsCount;

            for (let i = 0; i < correctCount; i++) {
                result[i] = 'correct';
            }

            if (errorsCount === 1) {
                const errorPosition = Math.min(completed - 1, Math.floor(completed / 2));
                if (errorPosition < correctCount) {
                    result[errorPosition] = 'incorrect';
                    if (completed - 1 !== errorPosition) {
                        result[completed - 1] = 'correct';
                    }
                } else {
                    result[completed - 1] = 'incorrect';
                }
            } else {
                for (let i = 0; i < errorsCount; i++) {
                    const errorPos = correctCount + i;
                    if (errorPos < completed) {
                        result[errorPos] = 'incorrect';
                    }
                }
            }
        } else {
            for (let i = 0; i < completed; i++) result[i] = 'correct';
        }

        return result;
    }

    return Array(total).fill('pending');
};

// Funções para o indicador de progresso circular
const getSegmentDashArray = (totalSegments: number): string => {
    const circumference = 2 * Math.PI * 32;
    const segmentLength = circumference / totalSegments;
    const gapLength = Math.max(3, segmentLength * 0.25);
    const strokeLength = segmentLength - gapLength;

    return `${strokeLength} ${circumference - strokeLength}`;
};

const getSegmentDashOffset = (totalSegments: number, segmentIndex: number): number => {
    const circumference = 2 * Math.PI * 32;
    const segmentLength = circumference / totalSegments;
    return circumference - (segmentLength * segmentIndex);
};

// Função para calcular a posição X de cada fase no padrão diagonal
const getPhaseXPosition = (phaseIndex: number): number => {
    const pattern = [0, 55, 110, 55];
    return pattern[phaseIndex % pattern.length];
};

// Função para rolar para a fase atual
const scrollToCurrentPhase = () => {
    if (!props.meta?.current_phase_id) {
        console.warn('Nenhuma fase atual identificada para scroll');
        return;
    }

    const currentPhaseId = props.meta.current_phase_id;
    const phaseElement = document.querySelector(`[data-phase-id="${currentPhaseId}"]`);

    if (phaseElement) {
        phaseElement.scrollIntoView({
            block: 'center',
            inline: 'center'
        });

        console.log(`Scroll automático para fase ${currentPhaseId}`);
    } else {
        console.warn(`Elemento da fase ${currentPhaseId} não encontrado para scroll`);
    }
};

// Adicionar event listener ao montar o componente
onMounted(() => {
    window.addEventListener('resize', updateWindowWidth);

    // Configurar Intersection Observer para scroll infinito
    if (sentinelRef.value) {
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore.value) {
                    loadMorePhases();
                }
            },
            { rootMargin: '100px' }
        );

        observer.observe(sentinelRef.value);

        onUnmounted(() => observer.disconnect());
    }

    // Scroll automático para fase atual após carregar
    setTimeout(() => {
        scrollToCurrentPhase();
    }, 500);
});

// Remover event listener ao desmontar
onUnmounted(() => {
    window.removeEventListener('resize', updateWindowWidth);
});
</script>

<template>
    <Head title="Aprender Jogando - Scroll Infinito" />

    <AppLayout>
        <div class="container py-8 px-4">
            <div class="max-w-4xl mx-auto">

                <!-- Debug Panel Component (apenas para admins) -->
                <div v-if="isAdmin" class="fixed top-20 right-4 z-50">
                    <DebugPanel
                        :phases="loadedPhases"
                        :modules="loadedModules"
                        :journey="{
                            total_phases: meta.total_phases,
                            loaded_phases: meta.loaded_phases,
                            has_more: hasMore
                        }"
                        :user="props.user"
                        :show-module-view="showModuleView"
                        :should-use-modules="shouldUseModules"
                        :window-width="windowWidth"
                    />
                </div>

                <!-- Mapa de fases -->
                <div class="">
                    <!-- Visualização por Módulos -->
                    <div v-if="shouldUseModules">
                        <div
                            v-for="module in expandedModules"
                            :key="module.id"
                            class="relative mb-12"
                        >
                            <!-- Título do Módulo -->
                            <div class="flex justify-center mb-8">
                                <div class="px-4 py-2 bg-purple-500 text-white rounded-lg border-4 border-purple-700 shadow-[0_6px_0_theme(colors.purple.700)] font-bold">
                                    <h1 class="text-lg font-bold">{{ module.title }}</h1>
                                </div>
                            </div>

                            <!-- Referências dentro do módulo -->
                            <div
                                v-for="reference in module.references"
                                :key="`${module.id}-${reference.reference_uuid}`"
                                class="relative mb-8"
                            >
                                <!-- Cabeçalho da referência -->
                                <div class="flex justify-center mb-6">
                                    <div class="w-full max-w-2xl px-4 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg border-4 border-gray-300 dark:border-gray-600 shadow-[0_6px_0_theme(colors.gray.300)] dark:shadow-[0_6px_0_theme(colors.gray.600)] font-bold">
                                        <h2 class="text-md font-semibold text-center">{{ reference.reference_name }}</h2>
                                    </div>
                                </div>

                                <!-- Container das fases em trilha diagonal -->
                                <div class="trail-path mx-auto flex flex-col">
                                    <div
                                        v-for="(phase, phaseIndex) in reference.phases"
                                        :key="`phase-${phase.id}`"
                                        :data-phase-id="phase.id"
                                        class="phase-item"
                                        :style="{
                                            transform: `translateX(${getPhaseXPosition(phaseIndex)}px)`,
                                            marginBottom: '18px'
                                        }"
                                    >
                                        <Link
                                            :href="phase.is_blocked
                                                ? '#'
                                                : (phase.is_review
                                                    ? route('play.review', { referenceUuid: phase.reference_uuid, phase: phase.id })
                                                    : route('play.phase', { phaseId: phase.id }))"
                                            class="relative group transition-transform duration-300 block phase-link"
                                            :class="{
                                                'cursor-not-allowed': phase.is_blocked,
                                                'hover:scale-110': !phase.is_blocked,
                                                'cursor-pointer': !phase.is_blocked
                                            }"
                                            @click="phase.is_blocked ? $event.preventDefault() : null"
                                        >
                                            <!-- Container da bolinha da fase com progresso circular -->
                                            <div class="relative flex items-center justify-center w-18 h-18">
                                                <!-- Indicador de progresso circular -->
                                                <svg
                                                    v-if="!phase.is_review && getArticleStatus(phase).length > 0"
                                                    class="absolute w-18 h-18 transform -rotate-90"
                                                    viewBox="0 0 72 72"
                                                >
                                                    <!-- Círculo de fundo -->
                                                    <g>
                                                        <circle
                                                            v-for="(status, segmentIndex) in getArticleStatus(phase)"
                                                            :key="`bg-segment-${phase.id}-${segmentIndex}`"
                                                            cx="36"
                                                            cy="36"
                                                            r="32"
                                                            fill="none"
                                                            stroke="rgba(200,200,200,0.4)"
                                                            stroke-width="3"
                                                            stroke-linecap="round"
                                                            :stroke-dasharray="getSegmentDashArray(getArticleStatus(phase).length)"
                                                            :stroke-dashoffset="getSegmentDashOffset(getArticleStatus(phase).length, segmentIndex)"
                                                            :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                                        />
                                                    </g>
                                                    <!-- Segmentos de progresso coloridos -->
                                                    <g>
                                                        <circle
                                                            v-for="(status, segmentIndex) in getArticleStatus(phase)"
                                                            :key="`segment-${phase.id}-${segmentIndex}`"
                                                            cx="36"
                                                            cy="36"
                                                            r="32"
                                                            fill="none"
                                                            :stroke="status === 'correct' ? '#22c55e' : status === 'incorrect' ? '#ef4444' : 'transparent'"
                                                            stroke-width="3"
                                                            stroke-linecap="round"
                                                            :stroke-dasharray="getSegmentDashArray(getArticleStatus(phase).length)"
                                                            :stroke-dashoffset="getSegmentDashOffset(getArticleStatus(phase).length, segmentIndex)"
                                                            :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                                        />
                                                    </g>
                                                </svg>

                                                <!-- Bolinha da fase -->
                                                <div
                                                    :class="[
                                                        'w-16 h-16 rounded-full flex items-center justify-center phase-circle relative z-10',
                                                        phase.is_review && isPhaseComplete(phase) ? 'bg-purple-500' :
                                                        phase.is_review && phase.is_current ? 'bg-purple-600 animate-pulse' :
                                                        phase.is_review && phase.is_blocked ? 'bg-purple-400/50' :
                                                        phase.is_review ? 'bg-purple-400' :
                                                        isPhaseComplete(phase) ? 'bg-green-500' :
                                                        phase.is_current ? 'bg-blue-500 animate-pulse' :
                                                        phase.is_blocked ? 'bg-gray-400/50' :
                                                        'bg-yellow-500'
                                                    ]"
                                                    :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                                >
                                                    <component
                                                        :is="phase.is_review
                                                            ? Repeat
                                                            : (isPhaseComplete(phase)
                                                                ? CheckCircle
                                                                : getPhaseIcon(phase.id))"
                                                        class="w-6 h-6 text-white"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Balão "Começar!" para fase atual -->
                                            <div
                                                v-if="phase.is_current && !phase.is_blocked"
                                                class="absolute -top-7 left-1/2 transform -translate-x-1/2 z-30 animate-bounce-slow"
                                            >
                                                <div class="relative bg-white text-gray-600 px-3 py-2 rounded-lg shadow-lg border-1 border-gray-500 font-bold text-sm whitespace-nowrap">
                                                    Começar!
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-6 border-r-6 border-t-8 border-l-transparent border-r-transparent border-t-gray-500"></div>
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-[-1px] w-0 h-0 border-l-5 border-r-5 border-t-7 border-l-transparent border-r-transparent border-t-white"></div>
                                                </div>
                                            </div>

                                            <!-- Texto de revisão -->
                                            <div v-if="phase.is_review" class="text-[9px] text-center text-muted-foreground mt-1 leading-tight mx-auto" :class="{'opacity-60': phase.is_blocked}">
                                                {{ phase.progress?.needs_review ? `(${phase.progress.articles_to_review_count || 0})` : '' }} Revisão
                                            </div>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visualização Tradicional (por referência legal) -->
                    <div v-else>
                        <div
                            v-for="group in referenceGroups"
                            :key="group.uuid"
                            class="relative mb-8"
                        >
                            <!-- Cabeçalho do grupo -->
                            <div class="flex justify-center mb-6">
                                <div class="w-full max-w-2xl px-4 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg border-4 border-gray-300 dark:border-gray-600 shadow-[0_6px_0_theme(colors.gray.300)] dark:shadow-[0_6px_0_theme(colors.gray.600)] font-bold">
                                    <h2 class="text-md font-semibold text-center">{{ group.name }}</h2>
                                </div>
                            </div>

                            <!-- Container das fases -->
                            <div class="trail-path mx-auto flex flex-col">
                                <div
                                    v-for="(phase, phaseIndex) in group.phases"
                                    :key="`phase-${phase.id}`"
                                    :data-phase-id="phase.id"
                                    class="phase-item"
                                    :style="{
                                        transform: `translateX(${getPhaseXPosition(phaseIndex)}px)`,
                                        marginBottom: '18px'
                                    }"
                                >
                                    <Link
                                        :href="phase.is_blocked
                                            ? '#'
                                            : (phase.is_review
                                                ? route('play.review', { referenceUuid: phase.reference_uuid, phase: phase.id })
                                                : route('play.phase', { phaseId: phase.id }))"
                                        class="relative group transition-transform duration-300 block phase-link"
                                        :class="{
                                            'cursor-not-allowed': phase.is_blocked,
                                            'hover:scale-110': !phase.is_blocked,
                                            'cursor-pointer': !phase.is_blocked
                                        }"
                                        @click="phase.is_blocked ? $event.preventDefault() : null"
                                    >
                                        <!-- Mesmo código da bolinha e progresso circular -->
                                        <div class="relative flex items-center justify-center w-18 h-18">
                                            <svg
                                                v-if="!phase.is_review && getArticleStatus(phase).length > 0"
                                                class="absolute w-18 h-18 transform -rotate-90"
                                                viewBox="0 0 72 72"
                                            >
                                                <g>
                                                    <circle
                                                        v-for="(status, segmentIndex) in getArticleStatus(phase)"
                                                        :key="`bg-segment-${phase.id}-${segmentIndex}`"
                                                        cx="36"
                                                        cy="36"
                                                        r="32"
                                                        fill="none"
                                                        stroke="rgba(200,200,200,0.4)"
                                                        stroke-width="3"
                                                        stroke-linecap="round"
                                                        :stroke-dasharray="getSegmentDashArray(getArticleStatus(phase).length)"
                                                        :stroke-dashoffset="getSegmentDashOffset(getArticleStatus(phase).length, segmentIndex)"
                                                        :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                                    />
                                                </g>
                                                <g>
                                                    <circle
                                                        v-for="(status, segmentIndex) in getArticleStatus(phase)"
                                                        :key="`segment-${phase.id}-${segmentIndex}`"
                                                        cx="36"
                                                        cy="36"
                                                        r="32"
                                                        fill="none"
                                                        :stroke="status === 'correct' ? '#22c55e' : status === 'incorrect' ? '#ef4444' : 'transparent'"
                                                        stroke-width="3"
                                                        stroke-linecap="round"
                                                        :stroke-dasharray="getSegmentDashArray(getArticleStatus(phase).length)"
                                                        :stroke-dashoffset="getSegmentDashOffset(getArticleStatus(phase).length, segmentIndex)"
                                                        :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                                    />
                                                </g>
                                            </svg>

                                            <div
                                                :class="[
                                                    'w-16 h-16 rounded-full flex items-center justify-center phase-circle relative z-10',
                                                    phase.is_review && isPhaseComplete(phase) ? 'bg-purple-500' :
                                                    phase.is_review && phase.is_current ? 'bg-purple-600 animate-pulse' :
                                                    phase.is_review && phase.is_blocked ? 'bg-purple-400/50' :
                                                    phase.is_review ? 'bg-purple-400' :
                                                    isPhaseComplete(phase) ? 'bg-green-500' :
                                                    phase.is_current ? 'bg-blue-500 animate-pulse' :
                                                    phase.is_blocked ? 'bg-gray-400/50' :
                                                    'bg-yellow-500'
                                                ]"
                                                :style="phase.is_blocked ? 'opacity: 0.6;' : ''"
                                            >
                                                <component
                                                    :is="phase.is_review
                                                        ? Repeat
                                                        : (isPhaseComplete(phase)
                                                            ? CheckCircle
                                                            : getPhaseIcon(phase.id))"
                                                    class="w-6 h-6 text-white"
                                                />
                                            </div>
                                        </div>

                                        <div
                                            v-if="phase.is_current && !phase.is_blocked"
                                            class="absolute -top-7 left-1/2 transform -translate-x-1/2 z-30 animate-bounce-slow"
                                        >
                                            <div class="relative bg-white text-gray-600 px-3 py-2 rounded-lg shadow-lg border-1 border-gray-500 font-bold text-sm whitespace-nowrap">
                                                Começar!
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-6 border-r-6 border-t-8 border-l-transparent border-r-transparent border-t-gray-500"></div>
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-[-1px] w-0 h-0 border-l-5 border-r-5 border-t-7 border-l-transparent border-r-transparent border-t-white"></div>
                                            </div>
                                        </div>

                                        <div v-if="phase.is_review" class="text-[9px] text-center text-muted-foreground mt-1 leading-tight mx-auto" :class="{'opacity-60': phase.is_blocked}">
                                            {{ phase.progress?.needs_review ? `(${phase.progress.articles_to_review_count || 0})` : '' }} Revisão
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sentinel para scroll infinito -->
                <div
                    ref="sentinelRef"
                    v-if="hasMore"
                    class="flex justify-center py-8"
                >
                    <div v-if="isLoadingMore" class="flex flex-col items-center gap-2">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                        <span class="text-sm text-muted-foreground">Carregando mais fases...</span>
                    </div>
                </div>

                <!-- Indicador de fim -->
                <div v-if="!hasMore" class="text-center py-8">
                    <div class="inline-block px-6 py-3 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 rounded-lg">
                        <span class="text-lg">🎉</span>
                        <span class="ml-2 font-semibold">Você chegou ao fim! Parabéns pelo progresso!</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Classes customizadas para tamanho 18 (72px) */
.w-18 {
    width: 4.5rem;
}

.h-18 {
    height: 4.5rem;
}

/* Animação de bounce lento */
@keyframes bounce-slow {
    0%, 20%, 53%, 80%, 100% {
        animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
        transform: translate3d(-50%, 0, 0);
    }
    40%, 43% {
        animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
        transform: translate3d(-50%, -8px, 0);
    }
    70% {
        animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
        transform: translate3d(-50%, -4px, 0);
    }
    90% {
        transform: translate3d(-50%, -2px, 0);
    }
}

.animate-bounce-slow {
    animation: bounce-slow 2s infinite;
}

/* Classes para as setas do balão */
.border-t-8 { border-top-width: 8px; }
.border-t-7 { border-top-width: 7px; }
.border-t-6 { border-top-width: 6px; }
.border-t-5 { border-top-width: 5px; }
.border-l-6 { border-left-width: 6px; }
.border-r-6 { border-right-width: 6px; }
.border-l-5 { border-left-width: 5px; }
.border-r-5 { border-right-width: 5px; }
.border-l-3 { border-left-width: 3px; }
.border-r-3 { border-right-width: 3px; }

/* Estilo para as bolinhas */
.phase-circle {
    box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.2), 0 3px 4px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
    width: 3.5rem !important;
    height: 3.5rem !important;
}

.phase-circle svg {
    width: 1.25rem !important;
    height: 1.25rem !important;
}

/* Container da trilha diagonal */
.trail-path {
    position: relative;
    padding: 20px 0;
    width: 174px;
    margin: 0 auto;
}

/* Item individual da fase */
.phase-item {
    position: relative;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.phase-item:last-child {
    margin-bottom: 0 !important;
}

/* Link da fase */
.phase-link {
    position: relative;
    z-index: 10;
    display: inline-block;
}

/* Responsividade */
@media (max-width: 640px) {
    .trail-path {
        width: 150px;
    }

    .phase-item[style*="translateX(55px)"] {
        transform: translateX(37px) !important;
    }

    .phase-item[style*="translateX(110px)"] {
        transform: translateX(75px) !important;
    }
}
</style>