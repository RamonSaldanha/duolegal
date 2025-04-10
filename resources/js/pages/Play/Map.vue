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

// Determina a fase atual do jogo de forma mais precisa
const determineGlobalCurrentPhase = (phases: Phase[]): Phase | null => {
    // Primeiro, verificar se há uma fase que é explicitamente marcada como atual pelo backend
    // Isso é determinado pelo campo reference_current_phase
    const currentPhasesByReference = new Map<string, Phase>();

    // Agrupar fases por referência e encontrar a fase atual para cada referência
    for (const phase of phases) {
        if (phase.reference_current_phase === phase.phase_number && !phase.is_blocked) {
            currentPhasesByReference.set(phase.reference_uuid, phase);
        }
    }

    // Se há apenas uma referência com fase atual, retornar essa fase
    if (currentPhasesByReference.size === 1) {
        return Array.from(currentPhasesByReference.values())[0];
    }

    // Se há múltiplas referências, precisamos determinar qual é a atual
    // Prioridade: fases de revisão não completas > fases regulares não completas > qualquer fase não bloqueada

    // Verificar todas as fases atuais por referência
    const currentPhases = Array.from(currentPhasesByReference.values());

    // Prioridade 1: Fases de revisão não completas
    const reviewPhase = currentPhases
        .filter(p => p.is_review && !p.is_complete)
        .sort((a, b) => a.phase_number - b.phase_number)[0];

    if (reviewPhase) {
        return reviewPhase;
    }

    // Prioridade 2: Fases regulares não completas
    const incompletePhase = currentPhases
        .filter(p => !p.is_review && !isPhaseComplete(p))
        .sort((a, b) => a.phase_number - b.phase_number)[0];

    if (incompletePhase) {
        return incompletePhase;
    }

    // Prioridade 3: Qualquer fase não bloqueada, ordenada por número
    // Se não encontramos nenhuma fase atual, procurar a primeira fase não bloqueada
    return phases
        .filter(p => !p.is_blocked)
        .sort((a, b) => a.phase_number - b.phase_number)[0] || null;
};

// Cache da fase atual global
const globalCurrentPhase = computed(() => determineGlobalCurrentPhase(props.phases));

// Verifica se é a fase atual (simplificado)
const isCurrentPhase = (phase: Phase): boolean => {
    return phase.is_current || false;
};
// Obtém o status de cada artigo na fase (acerto, erro, não respondido)
const getArticleStatus = (phase: Phase): string[] => {
    if (phase.is_review) {
        return []; // Revisão não tem status por artigo visível no mapa
    }
    if (phase.progress && phase.progress.article_status) {
        return phase.progress.article_status;
    }
    // Fallback (pode ser removido se o backend sempre fornecer article_status)
    const status: string[] = [];
    const total = phase.article_count || 0;
    const completed = phase.progress?.completed || 0; // 'completed' agora significa tentados
    const correct = (phase.progress?.article_status?.filter(s => s === 'correct').length) || 0; // Contar corretos se disponível

    // Estimativa se article_status não estiver disponível:
    // Se is_complete (fully_complete) -> tudo verde
    if(phase.is_complete) return Array(total).fill('correct');
    // Se não completo, mas tentado -> alguns vermelhos/amarelos
    // Melhor confiar que o backend enviará article_status
    return Array(total).fill('pending'); // Padrão se não houver dados
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

// Valores responsivos para os conectores
const getConnectorWidth = () => windowWidth.value <= 640 ? "235px" : "220px";
const getConnectorHeight = () => windowWidth.value <= 640 ? "90px" : "100px";
const getConnectorSpacing = (index: number) => `${index * (windowWidth.value <= 640 ? 90 : 100)}px`;

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
        <!-- Cabeçalho -->
        <div class="mb-4 flex justify-center items-center max-w-[500px] mx-auto mt-8">
          <div class="relative">
            <div class="absolute -top-[-0px] -left-28 bg-card dark:bg-gray-800 rounded-lg px-3 py-1 shadow-md text-sm font-medium speech-bubble text-foreground">
            Vamos por aqui!
            </div>
            <img src="/img/arara-e-preguica-mapa.png" alt="Logo" class="w-32 sm:w-40" />
            <div class="absolute -top-[25px] -right-12 bg-card dark:bg-gray-800 rounded-lg px-3 py-1 shadow-md text-sm font-medium speech-bubble text-foreground">
            Eaí... Vamos começar?
            </div>
            <div class="absolute -top-[-10px] -right-16 bg-card dark:bg-gray-800 rounded-lg px-3 py-1 shadow-md text-sm font-medium speech-bubble text-foreground">
            Beleza!
            </div>
          </div>
        </div>

        <!-- Mapa de fases -->
        <!-- Mapa de fases -->
        <div class="">
          <div
            v-for="(group, index) in referenceGroups"
            :key="group.uuid"
            class="relative"
          >
            <div class="flex justify-center mb-4">
              <div class="px-5 py-2 bg-primary/10 rounded">
                <h2 class="text-lg font-bold">{{ group.name }}</h2>
              </div>
            </div>

            <!-- Container da trilha -->
            <div class="relative trail-container mx-auto">
            <!-- Linhas conectoras (ajustar lógica de cor/opacidade) -->
              <div
                v-for="(_, phaseIndex) in group.phases.slice(0, -1)"
                :key="`connector-${phaseIndex}`"
                class="absolute left-0 w-full -z-10"
                :style="{ top: getConnectorSpacing(phaseIndex) }"
              >
                <div class="absolute top-[40px] left-0" :style="{ width: getConnectorWidth(), height: getConnectorHeight() }">
                  <svg
                    :width="getConnectorWidth()"
                    height="108"
                    :viewBox="`0 0 ${parseInt(getConnectorWidth())} 108`"
                    :style="{ height: getConnectorHeight(), width: getConnectorWidth() }"
                  >
                    <path
                      :d="phaseIndex % 2 === 0
                        ? `M160,0 C145,10 130,25 ${160-25*Math.sin(0.5)},50 S80,90 60,108`
                        : `M60,0 C75,10 90,25 ${60+25*Math.sin(0.5)},50 S140,90 160,108`"
                      :stroke="!group.phases[phaseIndex + 1].is_blocked ? '#432818' : 'currentColor'"
                      stroke-width="3"
                      :stroke-opacity="!group.phases[phaseIndex + 1].is_blocked ? '0.8' : '0.3'"
                      stroke-dasharray="8,6"
                      fill="none"
                    />
                  </svg>
                </div>
              </div>

              <!-- Fases -->
              <div class="space-y-6">
                <div
                  v-for="(phase, phaseIndex) in group.phases"
                  :key="`phase-${phase.id}`"
                  class="relative phase-item"
                >
                  <div class="flex items-center justify-center">
                    <div
                      :class="`flex ${phaseIndex % 2 === 0 ? 'justify-end ml-auto me-[10px]' : 'justify-start mr-auto ms-[10px]'}`"
                      style="width: 55%;"
                    >
                      <!-- :href agora usa phase.id e verifica is_blocked/is_current -->
                      <Link
                        :href="phase.is_blocked
                            ? '#'
                            : (phase.is_review
                                // Rota de revisão ainda usa referenceUuid e phase (ID global)
                                ? route('play.review', { referenceUuid: phase.reference_uuid, phase: phase.id })
                                // Rota de fase AGORA usa o nome do parâmetro 'phaseId' e passa phase.id
                                : route('play.phase', { phaseId: phase.id }))"
                        class="relative group transition-transform duration-300"
                        :class="{
                            'cursor-not-allowed': phase.is_blocked,
                            'hover:scale-110': !phase.is_blocked,
                            'cursor-pointer': !phase.is_blocked
                        }"
                        :style="`margin-${phaseIndex % 2 === 0 ? 'right' : 'left'}: -5px;`"
                        @click="phase.is_blocked ? $event.preventDefault() : null"
                    >
                          <!-- Bolinha da fase (lógica de cor ajustada) -->
                          <div
                              :class="[
                                  'w-16 h-16 rounded-full flex items-center justify-center phase-circle',
                                  phase.is_review && phase.is_blocked ? 'bg-purple-400/50' : // Revisão bloqueada (mais claro)
                                  phase.is_review && !phase.is_blocked && !phase.is_current ? 'bg-purple-500' : // Revisão disponível mas não atual
                                  phase.is_review && phase.is_current ? 'bg-purple-600 animate-pulse' : // Revisão ATUAL
                                  phase.is_current ? 'bg-blue-500 animate-pulse' : // Fase regular ATUAL
                                  phase.is_blocked ? 'bg-gray-400/50' : // Fase bloqueada (mais claro)
                                  isPhaseComplete(phase) ? 'bg-green-500' : // Fase completa (verde)
                                  !phase.is_blocked ? 'bg-yellow-500' : // Fase disponível mas incompleta (amarelo/laranja?)
                                  'bg-gray-400' // Fallback
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

                          <!-- Badge com número da fase (ID global) -->
                          <Badge class="absolute -top-2 -right-2 bg-primary" :class="{'opacity-60': phase.is_blocked}">
                              {{ phase.id }}
                          </Badge>

                          <!-- Indicador de progresso -->
                          <div v-if="!phase.is_review" class="mt-1 flex justify-center gap-1 h-[10px]" :class="{'opacity-60': phase.is_blocked}">
                            <span
                              v-for="(status, index_status) in getArticleStatus(phase)"
                              :key="`status-${phase.id}-${index_status}`"
                              class="w-2 h-2 rounded-full transition-colors duration-300"
                              :class="{
                                'bg-green-500': status === 'correct',
                                'bg-red-500': status === 'incorrect',
                                'bg-muted': status === 'pending'
                              }"
                            ></span>
                          </div>
                          <div class="text-sm text-center text-muted-foreground h-[10px]" v-else :class="{'opacity-60': phase.is_blocked}">
                            {{ phase.progress?.needs_review ? `(${phase.progress.articles_to_review_count || 0})` : '' }} Revisão
                          </div>

                          <!-- DEBUG (Ajustar para novos dados) -->
                          <div v-if="false" class="text-[10px] absolute -bottom-12 w-[200px] text-left text-muted-foreground" :style="phaseIndex % 2 === 0 ? 'right: -10px;' : 'left: -10px;'">
                            ID: {{ phase.id }} |
                            Comp: {{ phase.is_complete }} |
                            Block: {{ phase.is_blocked }} |
                            Curr: {{ phase.is_current }} |
                            Rev: {{ phase.is_review }} |
                            <span v-if="phase.is_review">Needs?: {{ phase.progress?.needs_review }}</span>
                            <span v-else>Status: {{ JSON.stringify(phase.progress?.article_status) }}</span>

                          </div>

                      </Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Conector entre grupos (ajustar lógica de cor/opacidade) -->
            <div v-if="index < referenceGroups.length - 1" class="relative mx-auto mt-0 mb-0 group-connector" style="height: 100px; max-width: 220px;">
                <div class="absolute w-full h-full -z-10">
                  <svg width="220" height="100" viewBox="0 0 220 100">
                    <!-- Cor/opacidade baseada se a *primeira fase do próximo grupo* está bloqueada -->
                    <path
                      :d="(group.phases.length - 1) % 2 === 0
                          ? `M160,0 C145,10 130,25 ${160-25*Math.sin(0.5)},50 S80,90 60,108`
                          : `M60,0 C75,10 90,25 ${60+25*Math.sin(0.5)},50 S140,90 160,108`"
                      :stroke="!referenceGroups[index+1]?.phases[0]?.is_blocked ? '#432818' : 'currentColor'"
                      stroke-width="3"
                      :stroke-opacity="!referenceGroups[index+1]?.phases[0]?.is_blocked ? '0.8' : '0.3'"
                      stroke-dasharray="8,6"
                      fill="none"
                    />
                  </svg>
                </div>
              </div>

          </div>
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
  box-shadow: inset 0 -5px 0 rgba(0, 0, 0, 0.2), 0 5px 6px rgba(0, 0, 0, 0.1);
  position: relative;
  overflow: hidden;
}

/* Contentor da trilha - mais estreito para manter as fases próximas */
.trail-container {
  position: relative;
  padding: 10px 0;
  max-width: 220px; /* Mais estreito para aproximar as fases */
}

/* Item de fase */
.phase-item {
  position: relative;
}

/* Espaçamento vertical reduzido */
.space-y-6 > * + * {
  margin-top: 1.25rem; /* Menos espaço vertical */
}

/* Responsividade */
@media (max-width: 640px) {
  .trail-container {
    max-width: 200px !important; /* Ainda mais estreito no mobile */
  }

  .phase-item > div > div {
    width: 52% !important; /* Ligeiramente mais próximo do centro */
  }

  .phase-item > div > div a {
    margin-left: 0 !important;
    margin-right: 0 !important;
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

/* Garantir que os conectores tenham tamanho fixo mesmo no mobile */
@media (max-width: 640px) {
  svg {
    width: 220px !important;
    transform: none !important;
  }

  /* Ajustes para o conector entre grupos no mobile */
  .group-connector {
    height: 25px !important;
  }

  .group-connector svg {
    height: 25px !important;
  }
}
</style>
