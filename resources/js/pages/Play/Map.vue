<script setup lang="ts">
// resources\js\pages\Play\Map.vue
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Book, FileText, Bookmark, CheckCircle, Star, Repeat } from 'lucide-vue-next';

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
}

interface Progress {
    completed: number;
    total: number;
    percentage: number;
    article_status?: string[];
    is_fully_complete?: boolean; // Vem do backend (true se 100% em tudo)
    all_attempted?: boolean;
    // Para revisão:
    is_complete?: boolean; // Vem do backend (true se não precisa revisar)
    needs_review?: boolean; // Vem do backend
    articles_to_review_count?: number; // Vem do backend
}

interface Phase {
    id: number; // ID Global da Fase
    title: string;
    reference_name: string;
    reference_uuid: string;
    article_count: number;
    difficulty: number;
    first_article: string | null;
    phase_number: number; // ID Global (mesmo que id)
    progress: Progress;
    is_blocked: boolean; // Definido pelo backend
    is_current: boolean; // Definido pelo backend
    is_review?: boolean;
    is_complete?: boolean; // Definido pelo backend (baseado em is_fully_complete ou status da revisão)
    chunk_index?: number; // Apenas para fases regulares
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
    modules?: any[]; // Dados dos módulos organizados
    journey?: JourneyInfo; // Informações da jornada atual
    user: User;
}>();

// Flag para alternar entre visualização tradicional e por módulos (padrão: módulos)
const showModuleView = ref(true);

// Agrupar fases por referência legal
const phasesByReference = computed<GroupedPhases>(() => {
    const grouped: GroupedPhases = {};

    if (props.phases) {
        props.phases.forEach(phase => {
            if (!grouped[phase.reference_uuid]) {
                grouped[phase.reference_uuid] = {
                    name: phase.reference_name,
                    phases: []
                };
            }

            grouped[phase.reference_uuid].phases.push(phase);
        });
    }

    return grouped;
});

// Verificar se há múltiplas leis (para mostrar opção de módulos)
const hasMultipleLaws = computed(() => {
    return Object.keys(phasesByReference.value).length > 1;
});

// Decidir se usar módulos ou visualização tradicional
const shouldUseModules = computed(() => {
    return showModuleView.value && hasMultipleLaws.value && props.modules && props.modules.length > 0;
});

const getPhaseIcon = (phaseNumber: number) => {
    const icons = [Book, FileText, Bookmark, Star, CheckCircle];
    return icons[(phaseNumber - 1) % icons.length];
};

const isPhaseComplete = (phase: Phase): boolean => {
    if (phase.is_review) {
        // Revisão completa significa que não precisa revisar
        return phase.progress?.is_complete || false;
    }
    // Fase regular completa significa 100% correto
    // Usar is_fully_complete se disponível, senão verificar status
    if(phase.progress?.is_fully_complete !== undefined) {
        return phase.progress.is_fully_complete;
    }
    // Verificar manualmente se todos os status são 'correct'
    return phase.progress?.article_status?.every(status => status === 'correct') || false;
};
// Obtém o status de cada artigo na fase (acerto, erro, não respondido)
const getArticleStatus = (phase: Phase): string[] => {
    if (phase.is_review) {
        return []; // Revisão não tem status por artigo visível no mapa
    }
    if (phase.progress && phase.progress.article_status) {
        return phase.progress.article_status;
    }
    // Fallback se o backend não fornecer article_status
    const total = phase.article_count || 0;
    
    // Se is_complete (fully_complete) -> tudo verde
    if(phase.is_complete) return Array(total).fill('correct');
    
    // Padrão se não houver dados
    return Array(total).fill('pending');
};

// Configurações dos conectores
const windowWidth = ref(window.innerWidth);

// Função para atualizar a largura da janela
const updateWindowWidth = () => {
  windowWidth.value = window.innerWidth;
};

// Adicionar event listener ao montar o componente
onMounted(() => {
  window.addEventListener('resize', updateWindowWidth);
});

// Remover event listener ao desmontar o componente
onUnmounted(() => {
  window.removeEventListener('resize', updateWindowWidth);
});

// Função para calcular a posição X de cada fase no padrão diagonal
const getPhaseXPosition = (phaseIndex: number): number => {
    // Padrão EXATO do Duolingo: esquerda, centro, direita, centro (repetindo)
    // 3 colunas bem definidas como na imagem de referência
    const pattern = [0, 55, 110, 55]; // 4 posições que se repetem
    return pattern[phaseIndex % pattern.length];
};

// Removidas as funções de conectores pois não usaremos mais estradas
// Converter o objeto de fases em um array para poder acessar o índice
const referenceGroups = computed(() => {
  return Object.entries(phasesByReference.value).map(([uuid, data]) => ({
    uuid,
    name: data.name,
    phases: data.phases
  }));
});

// Funções para o indicador de progresso circular
const getSegmentDashArray = (totalSegments: number): string => {
  const circumference = 2 * Math.PI * 32; // raio = 32 (mais próximo da bolinha)
  const segmentLength = circumference / totalSegments;
  const gapLength = Math.max(3, segmentLength * 0.25); // Gap maior - mínimo 3px ou 25% do segmento
  const strokeLength = segmentLength - gapLength;
  
  return `${strokeLength} ${circumference - strokeLength}`;
};

const getSegmentDashOffset = (totalSegments: number, segmentIndex: number): number => {
  const circumference = 2 * Math.PI * 32; // raio = 32 (mais próximo da bolinha)
  const segmentLength = circumference / totalSegments;
  return circumference - (segmentLength * segmentIndex);
};

</script>

<template>
  <Head title="Aprender Jogando" />

  <AppLayout>
    <div class="container py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <!-- Navegação de Jornadas -->
        <div v-if="props.journey && props.journey.total > 1" class="flex items-center justify-center mb-6 space-x-4">
          <!-- Botão Jornada Anterior -->
          <Link
            v-if="props.journey.has_previous"
            :href="route('play.map', { jornada: props.journey.current - 1 })"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:scale-105 active:scale-95"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="mr-2">
              <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
            </svg>
            Jornada Anterior
          </Link>
          
          <!-- Título da Jornada Atual -->
          <div class="px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-lg shadow-md">
            <h1 class="text-lg font-bold text-center">{{ props.journey.journey_title }}</h1>
            <p class="text-xs text-center opacity-90">{{ props.journey.phases_in_journey }} fases</p>
          </div>
          
          <!-- Botão Próxima Jornada -->
          <Link
            v-if="props.journey.has_next"
            :href="route('play.map', { jornada: props.journey.current + 1 })"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:scale-105 active:scale-95"
          >
            Próxima Jornada
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="ml-2">
              <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/>
            </svg>
          </Link>
        </div>

        <!-- Controle de alternância no topo -->
        <div v-if="hasMultipleLaws && props.modules && props.modules.length > 0" class="flex justify-center mb-6">
          <button 
            @click="showModuleView = !showModuleView"
            class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-full hover:from-blue-600 hover:to-purple-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-110 active:scale-95"
            :title="showModuleView ? 'Alternar para Visualização Tradicional' : 'Alternar para Visualização por Módulos'"
          >
            <!-- Ícone de binóculos - filled quando módulos ativados, outline quando tradicional -->
            <svg v-if="showModuleView" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="transition-transform duration-300" viewBox="0 0 16 16">
              <!-- Binóculos preenchidos (módulos ativados) -->
              <path d="M4.5 1A1.5 1.5 0 0 0 3 2.5V3h4v-.5A1.5 1.5 0 0 0 5.5 1zM7 4v1h2V4h4v.882a.5.5 0 0 0 .276.447l.895.447A1.5 1.5 0 0 1 15 7.118V13H9v-1.5a.5.5 0 0 1 .146-.354l.854-.853V9.5a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5v.793l.854.853A.5.5 0 0 1 7 11.5V13H1V7.118a1.5 1.5 0 0 1 .83-1.342l.894-.447A.5.5 0 0 0 3 4.882V4zM1 14v.5A1.5 1.5 0 0 0 2.5 16h3A1.5 1.5 0 0 0 7 14.5V14zm8 0v.5a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5V14zm4-11H9v-.5A1.5 1.5 0 0 1 10.5 1h1A1.5 1.5 0 0 1 13 2.5z"/>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="transition-transform duration-300" viewBox="0 0 16 16">
              <!-- Binóculos outline (visualização tradicional) -->
              <path d="M3 2.5A1.5 1.5 0 0 1 4.5 1h1A1.5 1.5 0 0 1 7 2.5V5h2V2.5A1.5 1.5 0 0 1 10.5 1h1A1.5 1.5 0 0 1 13 2.5v2.382a.5.5 0 0 0 .276.447l.895.447A1.5 1.5 0 0 1 15 7.118V14.5a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 14.5v-3a.5.5 0 0 1 .146-.354l.854-.853V9.5a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5v.793l.854.853A.5.5 0 0 1 7 11.5v3A1.5 1.5 0 0 1 5.5 16h-3A1.5 1.5 0 0 1 1 14.5V7.118a1.5 1.5 0 0 1 .83-1.342l.894-.447A.5.5 0 0 0 3 4.882zM4.5 2a.5.5 0 0 0-.5.5V3h2v-.5a.5.5 0 0 0-.5-.5zM6 4H4v.882a1.5 1.5 0 0 1-.83 1.342l-.894.447A.5.5 0 0 0 2 7.118V13h4v-1.293l-.854-.853A.5.5 0 0 1 5 10.5v-1A1.5 1.5 0 0 1 6.5 8h3A1.5 1.5 0 0 1 11 9.5v1a.5.5 0 0 1-.146.354l-.854.853V13h4V7.118a.5.5 0 0 0-.276-.447l-.895-.447A1.5 1.5 0 0 1 12 4.882V4h-2v1.5a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5zm4-1h2v-.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5zm4 11h-4v.5a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5zm-8 0H2v.5a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5z"/>
            </svg>
          </button>
        </div>

        <!-- Mapa de fases -->
        <div class="">
          <!-- Visualização por Módulos -->
          <div v-if="shouldUseModules">
            <div
              v-for="module in props.modules"
              :key="module.id"
              class="relative mb-12"
            >
              <!-- Título do Módulo -->
              <div class="flex justify-center mb-8">
                <div class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg shadow-md">
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
                  <div class="w-full max-w-2xl px-4 py-2 bg-background border border-border rounded-lg shadow-sm">
                    <h2 class="text-md font-semibold text-foreground text-center">{{ reference.reference_name }}</h2>
                  </div>
                </div>

                <!-- Container das fases em trilha diagonal -->
                <div class="trail-path mx-auto flex flex-col">
                  <div
                    v-for="(phase, phaseIndex) in reference.phases"
                    :key="`phase-${phase.id}`"
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
                        <!-- Indicador de progresso circular (só para fases não-review) -->
                        <svg 
                          v-if="!phase.is_review && getArticleStatus(phase).length > 0"
                          class="absolute w-18 h-18 transform -rotate-90"
                          viewBox="0 0 72 72"
                        >
                          <!-- Círculo de fundo para todos os segmentos (cinza claro) -->
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
                              phase.is_review && phase.is_blocked ? 'bg-purple-400/50' :
                              phase.is_review && !phase.is_blocked && !phase.is_current ? 'bg-purple-500' :
                              phase.is_review && phase.is_current ? 'bg-purple-600 animate-pulse' :
                              phase.is_current ? 'bg-blue-500 animate-pulse' :
                              phase.is_blocked ? 'bg-gray-400/50' :
                              isPhaseComplete(phase) ? 'bg-green-500' :
                              !phase.is_blocked ? 'bg-yellow-500' :
                              'bg-gray-400'
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
                <div class="w-full max-w-2xl px-4 py-2 bg-background border border-border rounded-lg shadow-sm">
                  <h2 class="text-md font-semibold text-foreground text-center">{{ group.name }}</h2>
                </div>
              </div>

              <!-- Container das fases em trilha diagonal -->
              <div class="trail-path mx-auto flex flex-col">
                <div
                  v-for="(phase, phaseIndex) in group.phases"
                  :key="`phase-${phase.id}`"
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
                      <!-- Indicador de progresso circular (só para fases não-review) -->
                      <svg 
                        v-if="!phase.is_review && getArticleStatus(phase).length > 0"
                        class="absolute w-18 h-18 transform -rotate-90"
                        viewBox="0 0 72 72"
                      >
                        <!-- Círculo de fundo para todos os segmentos (cinza claro) -->
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
                            phase.is_review && phase.is_blocked ? 'bg-purple-400/50' :
                            phase.is_review && !phase.is_blocked && !phase.is_current ? 'bg-purple-500' :
                            phase.is_review && phase.is_current ? 'bg-purple-600 animate-pulse' :
                            phase.is_current ? 'bg-blue-500 animate-pulse' :
                            phase.is_blocked ? 'bg-gray-400/50' :
                            isPhaseComplete(phase) ? 'bg-green-500' :
                            !phase.is_blocked ? 'bg-yellow-500' :
                            'bg-gray-400'
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
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>


        <!-- Instruções -->
        <div class="mt-12 p-6 bg-muted/50 rounded-lg text-center">
          <h3 class="text-xl font-bold mb-2">Como jogar?</h3>
          <p>Siga as fases e complete cada uma para dominar a legislação!</p>
          <p class="mt-2 text-sm text-muted-foreground">Escolha uma fase e pratique os artigos.</p>
          
          <div v-if="hasMultipleLaws && props.modules && props.modules.length > 0" class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <h4 class="font-semibold text-blue-800 mb-2">💡 Nova Funcionalidade: Módulos</h4>
            <p class="text-sm text-blue-700">
              Agora você pode alternar entre a visualização tradicional (uma lei por vez) e a visualização por módulos, 
              que intercala algumas fases de cada lei para um estudo mais variado!
            </p>
          </div>
          
          <div v-if="props.journey && props.journey.total > 1" class="mt-4 p-4 bg-amber-50 rounded-lg border border-amber-200">
            <h4 class="font-semibold text-amber-800 mb-2">🎯 Sistema de Jornadas</h4>
            <p class="text-sm text-amber-700 mb-2">
              Para melhorar o desempenho, suas fases foram organizadas em jornadas de até {{ 20 }} fases cada. 
              Use os botões de navegação acima para explorar diferentes jornadas!
            </p>
            <div class="flex items-center justify-center space-x-4 text-xs text-amber-600">
              <span>📍 Jornada Atual: {{ props.journey.current }}/{{ props.journey.total }}</span>
              <span>📊 {{ props.journey.phases_in_journey }} fases nesta jornada</span>
            </div>
          </div>
        </div>
      </div>
    </div>
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
</style>
