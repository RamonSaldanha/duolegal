<script setup lang="ts">
// resources\js\pages\Play\Map.vue
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Book, FileText, Bookmark, CheckCircle, Star, Repeat } from 'lucide-vue-next';

interface User {
    lives: number;
}

interface Progress {
    completed: number;
    total: number;
    percentage: number;
    article_status?: string[]; // Novo campo para status de cada artigo
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
    is_review?: boolean; // Novo campo para indicar se é uma fase de revisão
    is_complete?: boolean; // Novo campo para indicar se a fase está completa
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

// Verifica se a fase está completa (todos os artigos foram pelo menos tentados)
const isPhaseComplete = (phase: Phase): boolean => {
    // Para fase de revisão, usamos o campo is_complete direto do backend
    if (phase.is_review) {
        return phase.is_complete || false;
    }
    
    // Verificar se há dados de progresso
    if (!phase.progress || !phase.progress.article_status || phase.progress.article_status.length === 0) {
        return false;
    }
    
    // Verificar se existe algum artigo pendente
    const hasPendingArticle = phase.progress.article_status.some(status => status === 'pending');
    
    // A fase está completa quando não há artigos pendentes
    return !hasPendingArticle;
};

// Verifica se é a fase atual (primeira fase não totalmente tentada em cada referência)
const isCurrentPhase = (phase: Phase, phases: Phase[]): boolean => {
    // Se for uma fase de revisão, tem lógica diferente
    if (phase.is_review) {
        // Uma fase de revisão é atual se não estiver bloqueada
        return !phase.is_blocked;
    }
    
    // Se a fase estiver completa, não é a atual
    if (isPhaseComplete(phase)) {
        return false;
    }
    
    // Encontra a primeira fase não totalmente tentada na mesma referência
    // Considerando apenas fases regulares (não de revisão)
    const firstIncompletePhase = phases
        .filter(p => p.reference_uuid === phase.reference_uuid && !p.is_review)
        .sort((a, b) => a.phase_number - b.phase_number)
        .find(p => {
            // Verifica se há artigos pendentes (não tentados) na fase
            return p.progress && p.progress.article_status && 
                p.progress.article_status.some(status => status === 'pending');
        });
    
    // Se não encontrou nenhuma fase incompleta, a última fase regular é a atual
    if (!firstIncompletePhase) {
        const regularPhasesInReference = phases
            .filter(p => p.reference_uuid === phase.reference_uuid && !p.is_review)
            .sort((a, b) => a.phase_number - b.phase_number);
        
        if (regularPhasesInReference.length > 0) {
            return phase.phase_number === regularPhasesInReference[regularPhasesInReference.length - 1].phase_number;
        }
        return false;
    }
    
    // É a fase atual se for a primeira fase regular não totalmente tentada
    return firstIncompletePhase.phase_number === phase.phase_number;
};

// Obtém o status de cada artigo na fase (acerto, erro, não respondido)
const getArticleStatus = (phase: Phase): string[] => {
    // Para fases de revisão, retornar um array vazio (não tem indicadores de progresso)
    if (phase.is_review) {
        return [];
    }
    
    // Verifica se o backend forneceu informações detalhadas sobre o status dos artigos
    if (phase.progress && phase.progress.article_status) {
        return phase.progress.article_status;
    }
    
    // Fallback para o caso de o backend não fornecer as informações detalhadas
    // (compatibilidade com versões anteriores)
    const status: string[] = [];
    
    // Número de artigos tentados mas não completados (erros)
    // Vamos assumir que há pelo menos 1 erro se a fase não estiver completa e tiver algum progresso
    const hasProgress = phase.progress && phase.progress.completed > 0;
    const isIncomplete = phase.progress && phase.progress.completed < phase.article_count;
    const errorCount = hasProgress && isIncomplete ? 1 : 0;
    
    for (let i = 1; i <= phase.article_count; i++) {
        if (i <= phase.progress.completed) {
            // Artigos completados são considerados acertos (verde)
            status.push('correct');
        } else if (i <= phase.progress.completed + errorCount) {
            // Artigos tentados mas não completados são considerados erros (vermelho)
            status.push('incorrect');
        } else {
            // Artigos não tentados são considerados não respondidos (cinza)
            status.push('pending');
        }
    }
    
    return status;
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
</script>

<template>
  <Head title="Aprender Jogando" />

  <AppLayout>
    <div class="container py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <!-- Cabeçalho -->
        <div class="mb-4 text-center">
          <h1 class="text-3xl font-bold mb-1">Memorize legislação brincando</h1>
          <p class="text-muted-foreground mb-4">Escolha uma fase e comece a aprender os artigos de lei de forma divertida.</p>
        </div>

        <!-- Mapa de fases -->
        <div class="space-y-16">
          <div 
            v-for="(referenceData, referenceUuid) in phasesByReference" 
            :key="referenceUuid" 
            class="relative"
          >
            <div class="flex justify-center mb-4">
              <div class="px-5 py-2 bg-primary/10 rounded">
                <h2 class="text-lg font-bold">{{ referenceData.name }}</h2>
              </div>
            </div>

            <!-- Container da trilha -->
            <div class="relative trail-container mx-auto">
             <!-- Linhas diagonais conectoras (geradas dinamicamente) -->
              <div 
                v-for="(phase, index) in referenceData.phases.slice(0, -1)" 
                :key="`connector-${index}`"
                class="absolute left-0 w-full -z-10"
                :style="{
                  top: getConnectorSpacing(index),
                }"
              >
                <div class="absolute top-[50px] left-0" :style="{ width: getConnectorWidth(), height: getConnectorHeight() }">
                  <svg 
                  :width="getConnectorWidth()" 
                  height="100"
                  :viewBox="`0 0 ${parseInt(getConnectorWidth())} 100`" 
                  :style="{ height: getConnectorHeight(), width: getConnectorWidth() }"
                  >
                  <path 
                    :d="index % 2 === 0 ? 'M160,0 L60,100' : 'M60,0 L160,100'" 
                    stroke="currentColor" 
                    stroke-width="8"
                    class="text-muted-foreground/5" 
                    fill="none" 
                  />
                  </svg>
                </div>
              </div>

              <!-- Fases aproximadas no centro -->
              <div class="space-y-6">
                <div 
                  v-for="(phase, index) in referenceData.phases" 
                  :key="`phase-${phase.phase_number}`"
                  class="relative phase-item"
                >
                  <!-- Fase com posicionamento levemente alternado -->
                  <div class="flex items-center justify-center">
                    <!-- Fases ligeiramente à direita ou esquerda do centro -->
                    <div 
                      :class="`flex ${index % 2 === 0 ? 'justify-end ml-auto' : 'justify-start mr-auto'}`" 
                      style="width: 55%;"
                    >
                      <Link 
                          :href="phase.is_blocked 
                              ? '#' 
                              : (phase.is_review
                                  ? route('play.review', [phase.reference_uuid, phase.phase_number])
                                  : (props.user.lives > 0 && isCurrentPhase(phase, props.phases))
                                      ? route('play.phase', [phase.reference_uuid, phase.phase_number])
                                      : '#')"
                          class="relative group transition-transform duration-300"
                          :class="phase.is_blocked 
                              ? 'cursor-not-allowed' 
                              : (phase.is_review || (props.user.lives > 0 && isCurrentPhase(phase, props.phases)))
                                  ? 'hover:scale-110'
                                  : 'cursor-not-allowed'"
                          :style="`margin-${index % 2 === 0 ? 'right' : 'left'}: -5px;`"
                      >
                          <!-- Bolinha da fase -->
                          <div 
                              :class="[
                                  'w-16 h-16 rounded-full flex items-center justify-center phase-circle',
                                  phase.is_review && phase.is_blocked ? 'bg-purple-400' : // Revisão bloqueada
                                  phase.is_review ? 'bg-purple-500' : // Revisão não bloqueada
                                  phase.is_blocked ? 'bg-gray-400' : // Fase bloqueada
                                  isPhaseComplete(phase) ? 'bg-green-500' : // Fase completa
                                  isCurrentPhase(phase, props.phases) ? 'bg-blue-500' : // Fase atual
                                  'bg-gray-400' // Padrão: cinza
                              ]"
                          >
                              <component 
                                  :is="phase.is_review 
                                      ? Repeat 
                                      : (isPhaseComplete(phase) 
                                          ? CheckCircle 
                                          : getPhaseIcon(phase.phase_number))" 
                                  class="w-6 h-6 text-white" 
                              />
                          </div>
                          
                          <!-- Badge com número da fase -->
                          <Badge class="absolute -top-2 -right-2 bg-primary">
                              {{ phase.phase_number }}
                          </Badge>
                          
                          <!-- Indicador de progresso - mostrado apenas para fases regulares -->
                          <div v-if="!phase.is_review" class="mt-1 flex justify-center gap-1">
                            <span 
                              v-for="(status, index) in getArticleStatus(phase)" 
                              :key="index" 
                              class="w-2 h-2 rounded-full transition-colors duration-300"
                              :class="{
                                'bg-green-500': status === 'correct',
                                'bg-red-500': status === 'incorrect',
                                'bg-muted': status === 'pending'
                              }"
                            ></span>
                          </div>
                          
                          <!-- Para debug - remover depois -->
                          <div v-if="true" class="text-xs">
                            Phase: {{ phase.phase_number }} | 
                            Complete: {{ isPhaseComplete(phase) }} | 
                            Status: {{ JSON.stringify(phase.progress?.article_status) }} |
                            Blocked: {{ phase.is_blocked }} |
                            Review: {{ phase.is_review }} |
                            Current: {{ isCurrentPhase(phase, props.phases) }}
                          </div>
                      </Link>
                    </div>
                  </div>
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
  
  .absolute.top-[50px] {
    left: 0 !important;
    width: 220px !important;
  }
}
</style>
