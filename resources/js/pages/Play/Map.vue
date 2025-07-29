<script setup lang="ts">
// resources\js\pages\Play\Map.vue
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Book, FileText, Bookmark, CheckCircle, Star, Repeat } from 'lucide-vue-next';

interface User {
    lives: number;
    has_infinite_lives?: boolean;
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
    user: User;
}>();

// Flag para controlar exibição de indicadores de progresso e legendas
const showProgressIndicators = ref(true);

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
    // Padrão: 0, 60, 90, 60, 0, 60, 90, 60, 0...
    // Posições: esquerda (0), centro (60), direita (90), centro (60), esquerda (0)...
    const pattern = [0, 60, 90, 60]; // 4 posições que se repetem
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

</script>

<template>
  <Head title="Aprender Jogando" />

  <AppLayout>
    <div class="container py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <!-- Mapa de fases -->
        <div class="">
          <div
            v-for="group in referenceGroups"
            :key="group.uuid"
            class="relative mb-8"
          >
            <!-- Cabeçalho do grupo -->
            <div class="flex justify-center mb-6">
              <div class="px-4 py-2 bg-primary/10 rounded-lg">
                <h2 class="text-lg font-bold">{{ group.name }}</h2>
              </div>
            </div>

            <!-- Container das fases em trilha diagonal -->
            <div class="trail-path mx-auto flex flex-col gap-4">
              <div
                v-for="(phase, phaseIndex) in group.phases"
                :key="`phase-${phase.id}`"
                class="phase-item"
                :style="{
                  transform: `translateX(${getPhaseXPosition(phaseIndex)}px)`
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
                  <!-- Bolinha da fase -->
                  <div
                    :class="[
                        'w-16 h-16 rounded-full flex items-center justify-center phase-circle',
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

                  <!-- Badge com número da fase -->
                  <Badge 
                    v-if="showProgressIndicators"
                    class="absolute -top-1 -right-1 bg-primary text-xs h-5 w-5 flex items-center justify-center p-0 min-w-0" 
                    :class="{'opacity-60': phase.is_blocked}"
                  >
                    {{ phase.id }}
                  </Badge>

                  <!-- Indicador de progresso (pontos menores) -->
                  <div v-if="!phase.is_review && showProgressIndicators" class="mt-1 flex justify-center items-center gap-[1px] flex-wrap max-w-[48px] mx-auto" :class="{'opacity-60': phase.is_blocked}">
                    <span
                      v-for="(status, index_status) in getArticleStatus(phase)"
                      :key="`status-${phase.id}-${index_status}`"
                      class="w-1 h-1 rounded-full"
                      :class="{
                        'bg-green-500': status === 'correct',
                        'bg-red-500': status === 'incorrect',
                        'bg-gray-400': status === 'pending'
                      }"
                    ></span>
                  </div>
                  <div v-if="phase.is_review && showProgressIndicators" class="text-[9px] text-center text-muted-foreground mt-1 leading-tight mx-auto" :class="{'opacity-60': phase.is_blocked}">
                    {{ phase.progress?.needs_review ? `(${phase.progress.articles_to_review_count || 0})` : '' }} Revisão
                  </div>
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Controles -->
        <div class="mt-8 flex justify-center">
          <button 
            @click="showProgressIndicators = !showProgressIndicators"
            class="px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors text-sm"
          >
            {{ showProgressIndicators ? 'Ocultar' : 'Mostrar' }} Indicadores de Progresso
          </button>
        </div>

        <!-- Instruções -->
        <div class="mt-12 p-6 bg-muted/50 rounded-lg text-center">
          <h3 class="text-xl font-bold mb-2">Como jogar?</h3>
          <p>Siga as fases e complete cada uma para dominar a legislação!</p>
          <p class="mt-2 text-sm text-muted-foreground">Escolha uma fase e pratique os artigos.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>

/* Estilo para as bolinhas sem degradê branco */
.phase-circle {
  box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.2), 0 3px 4px rgba(0, 0, 0, 0.1);
  position: relative;
  overflow: hidden;
}

/* Container da trilha diagonal */
.trail-path {
  position: relative;
  padding: 20px 0;
  width: 154px; /* Largura para acomodar 3 posições com bolinhas maiores (64px + 90px) */
  margin: 0 auto;
}

/* Item individual da fase */
.phase-item {
  position: relative;
  transition: transform 0.3s ease;
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
    width: 134px; /* Menor no mobile */
    gap: 1rem; /* 16px no mobile */
  }

  /* Ajustar posições para mobile */
  .phase-item[style*="translateX(60px)"] {
    transform: translateX(45px) !important;
  }

  .phase-item[style*="translateX(90px)"] {
    transform: translateX(70px) !important;
  }

  .phase-circle {
    width: 3.5rem !important;
    height: 3.5rem !important;
  }

  .phase-circle svg {
    width: 1.25rem !important;
    height: 1.25rem !important;
  }
}
</style>
