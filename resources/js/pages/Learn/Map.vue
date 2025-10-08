<script setup lang="ts">
// resources\js\Pages\Learn\Map.vue - Versão otimizada idêntica ao Play/Map.vue mas sem indicadores circulares
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Book, FileText, Bookmark, CheckCircle, Star, Repeat, Trophy, ArrowLeft } from 'lucide-vue-next';
import UserAvatarGroup from '@/components/UserAvatarGroup.vue';
import PhaseUsersModal from '@/components/PhaseUsersModal.vue';

interface User {
    lives: number;
    has_infinite_lives?: boolean;
}

interface JourneyInfo {
    current: number;
    total: number;
    has_previous: boolean;
    has_next: boolean;
    phases_in_journey: number;
    total_phases: number;
    journey_title: string | null;
    current_phase_id?: number;
    was_auto_detected?: boolean;
}

interface Phase {
    id: number;
    reference_uuid: string;
    is_blocked: boolean;
    is_current: boolean;
    is_complete: boolean;
    is_review: boolean;
    progress?: {
        needs_review?: boolean;
        articles_to_review_count?: number;
    };
}

interface PhaseUser {
    id: number;
    name: string;
    initials?: string;
}

interface Challenge {
    uuid: string;
    title: string;
    description?: string;
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

interface ReferenceGroup {
    name: string;
    phases: Phase[];
}

interface GroupedPhases {
    [key: string]: ReferenceGroup;
}

const props = defineProps<{
    phases: Phase[];
    modules?: OptimizedModule[];
    journey?: JourneyInfo;
    user: User;
    is_challenge?: boolean;
    challenge?: Challenge;
    users_per_phase?: { [phaseId: number]: PhaseUser[] };
}>();

// Acesso à página para verificar se é admin
const page = usePage<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            is_admin: boolean;
            lives?: number;
            has_infinite_lives?: boolean;
        } | null;
    };
}>();

const isAdmin = computed(() => page.props.auth.user?.is_admin);

// Flag para alternar entre visualização tradicional e por módulos
const showModuleView = ref(true);

// Agrupar fases por referência legal
const phasesByReference = computed<GroupedPhases>(() => {
    const grouped: GroupedPhases = {};

    if (props.phases) {
        props.phases.forEach(phase => {
            if (!grouped[phase.reference_uuid]) {
                // Buscar nome da referência nos módulos
                let refName = 'Lei';
                if (props.modules) {
                    for (const module of props.modules) {
                        const ref = module.references.find(r => r.reference_uuid === phase.reference_uuid);
                        if (ref) {
                            refName = ref.reference_name;
                            break;
                        }
                    }
                }
                grouped[phase.reference_uuid] = {
                    name: refName,
                    phases: []
                };
            }

            grouped[phase.reference_uuid].phases.push(phase);
        });
    }

    return grouped;
});

// Verificar se há múltiplas leis
const hasMultipleLaws = computed(() => {
    return Object.keys(phasesByReference.value).length > 1;
});

// Decidir se usar módulos ou visualização tradicional
const shouldUseModules = computed(() => {
    return showModuleView.value && hasMultipleLaws.value && props.modules && props.modules.length > 0;
});

// Função para recuperar fases pelos IDs
const getPhasesByIds = (phaseIds: number[]): Phase[] => {
    if (!props.phases) return [];

    const phaseMap = new Map(props.phases.map(phase => [phase.id, phase]));
    return phaseIds.map(id => phaseMap.get(id)).filter(Boolean) as Phase[];
};

// Expandir módulos otimizados com dados completos
const expandedModules = computed(() => {
    if (!shouldUseModules.value || !props.modules) return [];

    return props.modules.map(module => ({
        ...module,
        references: module.references.map(ref => ({
            reference_name: ref.reference_name,
            reference_uuid: ref.reference_uuid,
            phases: getPhasesByIds(ref.phase_ids)
        }))
    }));
});

const getPhaseIcon = (phaseNumber: number) => {
    const icons = [Book, FileText, Bookmark, Star, CheckCircle];
    return icons[(phaseNumber - 1) % icons.length];
};

const isPhaseComplete = (phase: Phase): boolean => {
    return phase.is_complete;
};

// Configurações dos conectores
const windowWidth = ref(window.innerWidth);

const updateWindowWidth = () => {
    windowWidth.value = window.innerWidth;
};

// Converter objeto de fases em array
const referenceGroups = computed(() => {
    return Object.entries(phasesByReference.value).map(([uuid, data]) => ({
        uuid,
        name: data.name,
        phases: data.phases
    }));
});

// Função para calcular posição X no padrão diagonal
const getPhaseXPosition = (phaseIndex: number): number => {
    const pattern = [0, 55, 110, 55];
    return pattern[phaseIndex % pattern.length];
};

// =============== SISTEMA DE SCROLL AUTOMÁTICO ===============

// Função para rolar para a fase atual
const scrollToCurrentPhase = () => {
    if (!props.journey?.current_phase_id) {
        return;
    }

    const currentPhaseId = props.journey.current_phase_id;
    const phaseElement = document.querySelector(`[data-phase-id="${currentPhaseId}"]`);

    if (phaseElement) {
        phaseElement.scrollIntoView({
            block: 'center',
            inline: 'center'
        });
    }
};

// Função para detectar se existe fase atual na página atual
const hasCurrentPhaseInView = computed(() => {
    if (!props.journey?.current_phase_id || !props.phases) return false;

    return props.phases.some(phase =>
        phase.id === props.journey?.current_phase_id && phase.is_current
    );
});

// Estado para controlar a exibição do botão de scroll
const showScrollToCurrentButton = ref(false);

// Verificar se deve mostrar o botão de "Ir para fase atual"
const shouldShowScrollButton = computed(() => {
    return props.journey?.current_phase_id &&
           ((!hasCurrentPhaseInView.value) || showScrollToCurrentButton.value);
});

// Função para navegar para a jornada que contém a fase atual
const goToCurrentPhase = () => {
    if (!props.journey?.current_phase_id) return;

    if (hasCurrentPhaseInView.value) {
        scrollToCurrentPhase();
    } else {
        window.location.href = route('learn.map');
    }
};

// Adicionar event listener ao montar
onMounted(() => {
    window.addEventListener('resize', updateWindowWidth);

    // Scroll automático para a fase atual
    if (props.journey?.was_auto_detected && hasCurrentPhaseInView.value) {
        setTimeout(() => {
            scrollToCurrentPhase();
        }, 500);
    }

    // Mostrar botão de scroll se necessário
    if (!hasCurrentPhaseInView.value && props.journey?.current_phase_id) {
        showScrollToCurrentButton.value = true;
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', updateWindowWidth);
});

// =============== MODAL DE USUÁRIOS ===============
const selectedPhaseUsers = ref<PhaseUser[] | null>(null);
const showUsersModal = ref(false);

const openUsersModal = (phaseId: number) => {
    if (props.users_per_phase && props.users_per_phase[phaseId]) {
        selectedPhaseUsers.value = props.users_per_phase[phaseId];
        showUsersModal.value = true;
    }
};

const closeUsersModal = () => {
    showUsersModal.value = false;
    selectedPhaseUsers.value = null;
};

// =============== CONTADOR DE DESAFIOS - FASE ATUAL ===============
const currentPhaseNumber = computed(() => {
    if (!props.is_challenge || !props.phases) return 0;
    const currentPhase = props.phases.find(phase => phase.is_current);
    return currentPhase ? props.phases.indexOf(currentPhase) + 1 : 0;
});

const totalPhasesCount = computed(() => {
    if (!props.is_challenge || !props.phases) return 0;
    return props.phases.length;
});
</script>

<template>
    <Head title="Mapa de Aprendizado" />

    <AppLayout>
        <div class="container py-8 px-4">
            <div class="max-w-4xl mx-auto">

                <!-- Header de Desafios -->
                <div v-if="props.is_challenge && props.challenge" class="mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <Link
                            :href="route('challenges.index')"
                            class="flex items-center gap-2 text-primary hover:text-primary/80 transition-colors"
                        >
                            <ArrowLeft class="w-5 h-5" />
                            <span class="font-medium">Voltar para desafios</span>
                        </Link>
                    </div>

                    <!-- Título do Desafio com estilo gamificado -->
                    <div class="flex justify-center mb-6">
                        <div class="w-full max-w-3xl px-6 py-4 bg-gray-500 dark:bg-gray-600 text-white rounded-lg border-4 border-gray-700 dark:border-gray-800 shadow-[0_8px_0_theme(colors.gray.700)] dark:shadow-[0_8px_0_theme(colors.gray.800)] font-bold relative overflow-hidden">
                            <!-- Ícone de troféu decorativo no canto -->
                            <div class="absolute top-2 left-3 opacity-10">
                                <Trophy class="w-16 h-16" />
                            </div>

                            <!-- Conteúdo principal -->
                            <div class="relative z-10">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white/20 p-2.5 rounded-full">
                                            <Trophy class="w-7 h-7 text-white" />
                                        </div>
                                        <div>
                                            <h1 class="text-2xl font-bold leading-tight">{{ props.challenge.title }}</h1>
                                            <p v-if="props.challenge.description" class="text-sm text-white/95 mt-0.5 font-normal leading-tight">{{ props.challenge.description }}</p>
                                        </div>
                                    </div>

                                    <!-- Contador de progresso -->
                                    <div class="text-right bg-white/20 px-4 py-2 rounded-lg border-2 border-white/20 min-w-[100px]">
                                        <div class="text-3xl font-bold leading-none">
                                            {{ currentPhaseNumber }}/{{ totalPhasesCount }}
                                        </div>
                                        <div class="text-xs text-white/90 mt-1 font-semibold uppercase tracking-wide">Fase Atual</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botão flutuante "Ir para fase atual" -->
                <div
                    v-if="shouldShowScrollButton"
                    class="fixed bottom-20 left-4 z-50"
                >
                    <button
                        @click="goToCurrentPhase"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-full shadow-lg transition-all duration-200 hover:scale-105 flex items-center gap-2 font-medium text-sm"
                        title="Ir para a fase atual"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                        <span v-if="hasCurrentPhaseInView">Ir para minha fase</span>
                        <span v-else>Fase atual</span>
                    </button>
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
                                                : (props.is_challenge && props.challenge
                                                    ? route('challenges.phase', { challenge: props.challenge.uuid, phaseNumber: phase.id })
                                                    : (phase.is_review
                                                        ? route('play.review', { referenceUuid: phase.reference_uuid, phase: phase.id })
                                                        : route('play.phase', { phaseId: phase.id })))"
                                            class="relative group transition-transform duration-300 block phase-link"
                                            :class="{
                                                'cursor-not-allowed': phase.is_blocked,
                                                'hover:scale-110': !phase.is_blocked,
                                                'cursor-pointer': !phase.is_blocked
                                            }"
                                            @click="phase.is_blocked ? $event.preventDefault() : null"
                                        >
                                            <!-- Container da bolinha da fase SEM progresso circular -->
                                            <div class="relative flex items-center justify-center w-18 h-18">
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
                                                <!-- Balão de fala -->
                                                <div class="relative bg-white text-gray-600 px-3 py-2 rounded-lg shadow-lg border-1 border-gray-500 font-bold text-sm whitespace-nowrap">
                                                    Começar!
                                                    <!-- Seta do balão -->
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-6 border-r-6 border-t-8 border-l-transparent border-r-transparent border-t-gray-500"></div>
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-[-1px] w-0 h-0 border-l-5 border-r-5 border-t-7 border-l-transparent border-r-transparent border-t-white"></div>
                                                </div>
                                            </div>

                                            <!-- Texto de revisão (apenas para fases de revisão) -->
                                            <div v-if="phase.is_review" class="text-[9px] text-center text-muted-foreground mt-1 leading-tight mx-auto" :class="{'opacity-60': phase.is_blocked}">
                                                {{ phase.progress?.needs_review ? `(${phase.progress.articles_to_review_count || 0})` : '' }} Revisão
                                            </div>

                                            <!-- Avatares dos usuários (apenas para desafios) -->
                                            <div
                                                v-if="props.is_challenge && props.users_per_phase && props.users_per_phase[phase.id]"
                                                class="mt-2 flex justify-center"
                                                @click.prevent="openUsersModal(phase.id)"
                                            >
                                                <UserAvatarGroup
                                                    :users="props.users_per_phase[phase.id]"
                                                    :max-display="3"
                                                    size="sm"
                                                />
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

                            <!-- Container das fases em trilha diagonal -->
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
                                            : (props.is_challenge && props.challenge
                                                ? route('challenges.phase', { challenge: props.challenge.uuid, phaseNumber: phase.id })
                                                : (phase.is_review
                                                    ? route('play.review', { referenceUuid: phase.reference_uuid, phase: phase.id })
                                                    : route('play.phase', { phaseId: phase.id })))"
                                        class="relative group transition-transform duration-300 block phase-link"
                                        :class="{
                                            'cursor-not-allowed': phase.is_blocked,
                                            'hover:scale-110': !phase.is_blocked,
                                            'cursor-pointer': !phase.is_blocked
                                        }"
                                        @click="phase.is_blocked ? $event.preventDefault() : null"
                                    >
                                        <!-- Container da bolinha da fase SEM progresso circular -->
                                        <div class="relative flex items-center justify-center w-18 h-18">
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
                                            <!-- Balão de fala -->
                                            <div class="relative bg-white text-gray-600 px-3 py-2 rounded-lg shadow-lg border-1 border-gray-500 font-bold text-sm whitespace-nowrap">
                                                Começar!
                                                <!-- Seta do balão -->
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-6 border-r-6 border-t-8 border-l-transparent border-r-transparent border-t-gray-500"></div>
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-[-1px] w-0 h-0 border-l-5 border-r-5 border-t-7 border-l-transparent border-r-transparent border-t-white"></div>
                                            </div>
                                        </div>

                                        <!-- Texto de revisão (apenas para fases de revisão) -->
                                        <div v-if="phase.is_review" class="text-[9px] text-center text-muted-foreground mt-1 leading-tight mx-auto" :class="{'opacity-60': phase.is_blocked}">
                                            {{ phase.progress?.needs_review ? `(${phase.progress.articles_to_review_count || 0})` : '' }} Revisão
                                        </div>

                                        <!-- Avatares dos usuários (apenas para desafios) -->
                                        <div
                                            v-if="props.is_challenge && props.users_per_phase && props.users_per_phase[phase.id]"
                                            class="mt-2 flex justify-center"
                                            @click.prevent="openUsersModal(phase.id)"
                                        >
                                            <UserAvatarGroup
                                                :users="props.users_per_phase[phase.id]"
                                                :max-display="3"
                                                size="sm"
                                            />
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navegação de Jornadas no final -->
                <div v-if="props.journey && props.journey.total > 1" class="flex flex-col sm:flex-row items-center justify-center mt-12 space-y-3 sm:space-y-0 sm:space-x-4 w-full">
                    <!-- Botão Jornada Anterior -->
                    <Link
                        v-if="props.journey.has_previous"
                        :href="route('learn.map', { jornada: props.journey.current - 1 })"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-3 py-1.5 bg-green-500 text-white rounded-lg border-4 border-green-700 shadow-[0_6px_0_theme(colors.green.700)] hover:shadow-[0_4px_0_theme(colors.green.700)] hover:translate-y-[2px] active:shadow-[0_2px_0_theme(colors.green.700)] active:translate-y-[4px] transition-all duration-150 font-bold text-sm"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="mr-2">
                            <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
                        </svg>
                        Jornada Anterior
                    </Link>

                    <!-- Título da Jornada Atual -->
                    <div class="w-full sm:w-auto px-6 py-2 bg-amber-500 text-white rounded-lg border-4 border-amber-700 shadow-[0_6px_0_theme(colors.amber.700)] font-bold">
                        <h1 class="text-base font-bold text-center leading-tight">{{ props.journey.journey_title }}</h1>
                        <p class="text-xs text-center opacity-90 leading-tight mt-0.5">{{ props.journey.phases_in_journey }} fases</p>
                    </div>

                    <!-- Botão Próxima Jornada -->
                    <Link
                        v-if="props.journey.has_next"
                        :href="route('learn.map', { jornada: props.journey.current + 1 })"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-3 py-1.5 bg-blue-500 text-white rounded-lg border-4 border-blue-700 shadow-[0_6px_0_theme(colors.blue.700)] hover:shadow-[0_4px_0_theme(colors.blue.700)] hover:translate-y-[2px] active:shadow-[0_2px_0_theme(colors.blue.700)] active:translate-y-[4px] transition-all duration-150 font-bold text-sm"
                    >
                        Próxima Jornada
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="ml-2">
                            <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/>
                        </svg>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Modal de Usuários -->
        <PhaseUsersModal
            v-if="props.is_challenge"
            :show="showUsersModal"
            :users="selectedPhaseUsers || []"
            @close="closeUsersModal"
        />
    </AppLayout>
</template>

<style scoped>
/* Classes customizadas para tamanho 18 (72px) */
.w-18 {
    width: 4.5rem; /* 72px */
}

.h-18 {
    height: 4.5rem; /* 72px */
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
.border-t-8 {
    border-top-width: 8px;
}

.border-t-7 {
    border-top-width: 7px;
}

.border-t-6 {
    border-top-width: 6px;
}

.border-t-5 {
    border-top-width: 5px;
}

.border-l-6 {
    border-left-width: 6px;
}

.border-r-6 {
    border-right-width: 6px;
}

.border-l-5 {
    border-left-width: 5px;
}

.border-r-5 {
    border-right-width: 5px;
}

.border-l-3 {
    border-left-width: 3px;
}

.border-r-3 {
    border-right-width: 3px;
}

/* Estilo para as bolinhas sem degradê branco */
.phase-circle {
    box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.2), 0 3px 4px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
}

.phase-circle {
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
    width: 174px; /* Largura para acomodar padrão do Duolingo (110px + 64px da bolinha) */
    margin: 0 auto;
}

/* Item individual da fase */
.phase-item {
    position: relative;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Última fase sem margem inferior */
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
        width: 150px; /* Ajustado para mobile com padrão estendido */
    }

    /* Ajustar posições para mobile - padrão DUOLINGO proporcional */
    .phase-item[style*="translateX(55px)"] {
        transform: translateX(37px) !important;
    }

    .phase-item[style*="translateX(110px)"] {
        transform: translateX(75px) !important;
    }
}

/* Estilo para o botão flutuante */
.fixed.bottom-20.left-4 button {
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.fixed.bottom-20.left-4 button:hover {
    backdrop-filter: blur(15px);
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
}
</style>
