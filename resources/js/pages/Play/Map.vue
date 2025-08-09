<script setup lang="ts">
// resources\js\pages\Play\Map.vue
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Book, FileText, Bookmark, CheckCircle, Star, Repeat, Bug, X } from 'lucide-vue-next';
import DebugPanel from './DebugPanel.vue';

interface User {
  // Campos mínimos usados na UI de vidas
    lives: number;
    has_infinite_lives?: boolean;
  // Campos opcionais usados em debug
  id?: number;
  name?: string;
  email?: string;
  xp?: number;
}

interface JourneyInfo {
  // Índices de navegação da jornada
    current: number;
    total: number;
    has_previous: boolean;
    has_next: boolean;
    phases_in_journey: number;
    total_phases: number;
    journey_title: string | null;
  // Campos opcionais para debug
  id?: number;
  title?: string | null;
}

interface Progress {
    completed: number;
    total: number;
    percentage: number;
    is_fully_complete?: boolean; // Vem do backend (true se 100% em tudo)
    all_attempted?: boolean;
  has_errors?: boolean; // Novo campo otimizado em vez de article_status array
  // Se o backend enviar, usamos diretamente o status por artigo
  article_status?: Array<'correct' | 'incorrect' | 'pending'>;
  // Quantidade opcional de erros entre os concluídos
  errors_count?: number;
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

interface OptimizedReferenceGroup {
    reference_name: string;
    reference_uuid: string;
    phase_ids: number[]; // Apenas IDs das fases, não dados completos
}

interface OptimizedModule {
    id: number;
    title: string;
    references: OptimizedReferenceGroup[];
}

interface GroupedPhases {
    [key: string]: ReferenceGroup;
}

const props = defineProps<{
    phases: Phase[];
    modules?: OptimizedModule[]; // Dados dos módulos organizados (otimizados)
    journey?: JourneyInfo; // Informações da jornada atual
    user: User;
}>();

// Estado do painel de debug
const showDebugPanel = ref(false);

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
            debug_info?: {
                has_active_subscription: boolean;
                on_trial: boolean;
                subscribed: boolean;
                trial_ends_at: string | null;
            };
        } | null;
    };
}>();

const isAdmin = computed(() => page.props.auth.user?.is_admin);

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

// Função para recuperar fases pelos IDs (para módulos otimizados)
const getPhasesByIds = (phaseIds: number[]): Phase[] => {
    if (!props.phases) return [];
    
    const phaseMap = new Map(props.phases.map(phase => [phase.id, phase]));
    return phaseIds.map(id => phaseMap.get(id)).filter(Boolean) as Phase[];
};

// Expandir módulos otimizados com dados completos das fases (apenas quando necessário)
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
    if (phase.is_review) {
        // Revisão completa significa que não precisa revisar
        return phase.progress?.is_complete || false;
    }
    
    // Fase regular completa - usar dados otimizados
    // Prioridade 1: Verificar is_fully_complete (dados do backend)
    if (phase.progress?.is_fully_complete !== undefined) {
        return phase.progress.is_fully_complete;
    }
    
    // Prioridade 2: Verificar is_complete da fase (fallback)
    if (phase.is_complete !== undefined) {
        return phase.is_complete;
    }
    
    // Prioridade 3: Verificar se completed === total (último fallback)
    if (phase.progress?.completed !== undefined && phase.progress?.total !== undefined) {
        return phase.progress.completed === phase.progress.total && phase.progress.total > 0;
    }
    
    return false;
};
// Obtém o status de cada artigo na fase (acerto, erro, não respondido)
const getArticleStatus = (phase: Phase): Array<'correct' | 'incorrect' | 'pending'> => {
    if (phase.is_review) {
        return []; // Revisão não tem status por artigo visível no mapa
    }
    
  const total = phase.article_count || phase.progress?.total || 0;
  const completed = phase.progress?.completed || 0;
  const hasErrors = phase.progress?.has_errors || false;
    
    // 1) Preferir dados por-artigo do backend, se existirem
    // IDEAL: O backend deveria enviar algo como:
    // "article_status": ["correct", "incorrect", "correct", "correct", "pending", "pending"]
    // onde cada posição corresponde exatamente ao resultado de cada desafio
    const statuses = phase.progress?.article_status;
    if (statuses && statuses.length > 0) {
        const trimmed = statuses.slice(0, total);
        return trimmed.length < total
            ? [...trimmed, ...Array(total - trimmed.length).fill('pending')]
            : trimmed;
    }  // 2) Se a fase estiver 100% completa (e marcada pelo backend), tudo verde
    
    // Se is_complete (fully_complete) -> tudo verde
    if (phase.is_complete && phase.progress?.is_fully_complete) {
        return Array(total).fill('correct');
    }
    
    // 3) Fallback quando há progresso parcial
    if (completed > 0) {
        const result: Array<'correct' | 'incorrect' | 'pending'> = Array(total).fill('pending');
        
        if (hasErrors) {
            // Estratégia melhorada: distribuir erros de forma mais realista
            const errorsCountRaw = phase.progress?.errors_count;
            const errorsCount = Math.max(1, Math.min(completed, errorsCountRaw ?? 1));
            const correctCount = completed - errorsCount;
            
            // Em vez de agrupar erros no final, distribuir de forma mais natural
            // Simular padrão mais realista onde erros podem aparecer em qualquer posição
            
            // Marcar os corretos primeiro
            for (let i = 0; i < correctCount; i++) {
                result[i] = 'correct';
            }
            
            // Distribuir erros entre as posições respondidas
            // Se há poucos erros, colocar em posições intermediárias/finais
            if (errorsCount === 1) {
                // Um erro: colocar em posição intermediária ou próxima ao final
                const errorPosition = Math.min(completed - 1, Math.floor(completed / 2));
                // Realocar: mover um 'correct' para frente e colocar erro na posição
                if (errorPosition < correctCount) {
                    result[errorPosition] = 'incorrect';
                    // Mover o último correct para a posição final
                    if (completed - 1 !== errorPosition) {
                        result[completed - 1] = 'correct';
                    }
                } else {
                    result[completed - 1] = 'incorrect';
                }
            } else {
                // Múltiplos erros: distribuir nas posições finais
                for (let i = 0; i < errorsCount; i++) {
                    const errorPos = correctCount + i;
                    if (errorPos < completed) {
                        result[errorPos] = 'incorrect';
                    }
                }
            }
        } else {
            // Sem erros, todos os respondidos são corretos
            for (let i = 0; i < completed; i++) result[i] = 'correct';
        }
        
        return result;
    }    // Padrão se não houver dados
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

// Função para copiar o DEBUG completo (sem cortes), em JSON legível
const copyFullDebugInfo = async (): Promise<void> => {
  // Monta um payload simples e serializável (sem proxies do Vue)
  const safeUser = page.props.auth.user
    ? {
        id: page.props.auth.user.id,
        name: page.props.auth.user.name,
        email: page.props.auth.user.email,
        is_admin: page.props.auth.user.is_admin,
        lives: page.props.auth.user.lives ?? null,
        has_infinite_lives: page.props.auth.user.has_infinite_lives ?? null,
        debug_info: page.props.auth.user.debug_info ?? null,
      }
    : null;

  const payload = {
    app: { name: 'Memorize Direito', page: 'Play/Map' },
    timestamp: new Date().toISOString(),
    context: {
      hasMultipleLaws: hasMultipleLaws.value,
      shouldUseModules: shouldUseModules.value,
      showModuleView: showModuleView.value,
    },
    user: safeUser,
    journey: props.journey ?? null,
    counts: {
      phases: props.phases?.length ?? 0,
      modules: props.modules?.length ?? 0,
    },
    // Dados completos vindos do PlayController/Inertia
    phases: props.phases ?? [],
    modules: props.modules ?? [],
    // Variações derivadas para facilitar análise
    expandedModules: expandedModules.value ?? [],
    groupedByReference: referenceGroups.value ?? [],
  } as const;

  const text = [
    '=== DEBUG PLAY/MAP ===',
    `timestamp: ${payload.timestamp}`,
    '',
    'payload.json:',
    JSON.stringify(payload, null, 2),
    '=== END DEBUG ===',
  ].join('\n');

  try {
    await navigator.clipboard.writeText(text);
    alert('Debug completo copiado para a área de transferência!');
  } catch (err) {
    try {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'absolute';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      alert('Debug completo copiado (fallback)');
    } catch (fallbackErr) {
      console.error('Falha ao copiar debug:', fallbackErr);
      alert('Não foi possível copiar o debug. Veja o console.');
    }
  }
};

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

// Função para copiar informações de debug
const copyDebugInfo = async (): Promise<void> => {
  try {
    const debugData = {
      timestamp: new Date().toISOString(),
      user: props.user,
      journey: props.journey,
      phases_count: props.phases?.length || 0,
      modules_count: props.modules?.length || 0,
      phases: props.phases?.map(phase => ({
        id: phase.id,
        title: phase.title,
        backend_data: {
          is_complete: phase.is_complete,
          is_fully_complete: phase.progress?.is_fully_complete,
          all_attempted: phase.progress?.all_attempted,
          is_blocked: phase.is_blocked,
          is_current: phase.is_current,
          completed: phase.progress?.completed,
          total: phase.progress?.total
        },
        frontend_computed: {
          isPhaseComplete: isPhaseComplete(phase),
          should_be_green: isPhaseComplete(phase),
          css_class: isPhaseComplete(phase) ? 'bg-green-500' : (phase.is_current ? 'bg-blue-500' : (phase.is_blocked ? 'bg-gray-400' : 'bg-gray-300'))
        }
      })),
      modules: props.modules?.map(module => ({
        title: module.title,
        references: module.references?.map(ref => ({
          reference_name: ref.reference_name,
          reference_uuid: ref.reference_uuid,
          phase_ids: ref.phase_ids,
          phases_count: ref.phase_ids?.length || 0
        }))
      }))
    };

    const debugText = `=== DEBUG INFO - Memorize Direito Map ===
Timestamp: ${debugData.timestamp}

USER INFO:
- ID: ${debugData.user?.id}
- Name: ${debugData.user?.name}
- XP: ${debugData.user?.xp}

JOURNEY INFO:
- ID: ${debugData.journey?.id}
- Title: ${debugData.journey?.title}

GENERAL STATS:
- Total Phases: ${debugData.phases_count}
- Total Modules: ${debugData.modules_count}

PHASES ANALYSIS (first 10):
${debugData.phases?.slice(0, 10).map(phase => `
Phase ${phase.id} - ${phase.title}
Backend Data:
  • is_complete: ${phase.backend_data.is_complete}
  • is_fully_complete: ${phase.backend_data.is_fully_complete}
  • all_attempted: ${phase.backend_data.all_attempted}
  • is_blocked: ${phase.backend_data.is_blocked}
  • is_current: ${phase.backend_data.is_current}
  • completed/total: ${phase.backend_data.completed}/${phase.backend_data.total}
Frontend Computed:
  • isPhaseComplete(): ${phase.frontend_computed.isPhaseComplete}
  • Should be GREEN: ${phase.frontend_computed.should_be_green ? 'YES' : 'NO'}
  • CSS Class: ${phase.frontend_computed.css_class}
`).join('\n') || 'No phases data'}

MODULES INFO:
${debugData.modules?.map(module => `
Module: ${module.title}
References: ${module.references?.map(ref => `
  • ${ref.reference_name} (${ref.phases_count} fases): IDs ${ref.phase_ids?.join(', ') || 'None'}`).join('') || 'None'}
`).join('\n') || 'No modules data'}

=== END DEBUG INFO ===`;

    await navigator.clipboard.writeText(debugText);
    
    // Show success feedback
    alert('Debug info copiado para a área de transferência!');
    
  } catch (error) {
    console.error('Erro ao copiar debug info:', error);
    
    // Fallback: create a textarea and select text
    const textarea = document.createElement('textarea');
    textarea.value = JSON.stringify({
      error: 'Failed to copy to clipboard',
      user: props.user,
      journey: props.journey,
      phases_count: props.phases?.length,
      modules_count: props.modules?.length
    }, null, 2);
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    
    alert('Debug info básico copiado para a área de transferência (fallback)');
  }
};

</script>

<template>
  <Head title="Aprender Jogando" />

  <AppLayout>
    <div class="container py-8 px-4">
      <div class="max-w-4xl mx-auto">

        <!-- Botão de Debug (apenas para admins) -->
        <div v-if="isAdmin" class="fixed top-20 right-4 z-50">
          <button
            @click="showDebugPanel = !showDebugPanel"
            class="flex items-center gap-2 px-3 py-2 bg-red-500 text-white rounded-lg shadow-lg hover:bg-red-600 transition-colors"
          >
            <Bug class="w-4 h-4" />
            <span class="text-sm font-medium">Debug Map</span>
          </button>
        </div>

        <!-- Painel de Debug -->
        <div 
          v-if="isAdmin && showDebugPanel" 
          class="fixed inset-4 bg-white dark:bg-gray-900 border-2 border-red-500 rounded-lg shadow-2xl z-40 overflow-hidden flex flex-col"
        >
          <!-- Header do painel -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
            <h3 class="text-lg font-bold text-red-700 dark:text-red-300 flex items-center gap-2">
              <Bug class="w-5 h-5" />
              Debug - Dados do PlayController para Map
            </h3>
            <button
              @click="showDebugPanel = false"
              class="p-1 hover:bg-red-200 dark:hover:bg-red-800 rounded text-red-600 dark:text-red-400"
            >
              <X class="w-5 h-5" />
            </button>
          </div>
          
          <!-- Conteúdo do painel -->
          <div class="flex-1 overflow-auto p-4">
            <div class="space-y-6 text-xs">
              
              <!-- Seção: Props recebidas do Controller -->
              <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                <h4 class="font-bold text-blue-700 dark:text-blue-300 mb-3 text-sm">📨 Props do PlayController</h4>
                
                <!-- Phases -->
                <div class="mb-4">
                  <h5 class="font-semibold text-blue-600 dark:text-blue-400 mb-2">Phases ({{ props.phases?.length || 0 }} items)</h5>
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border max-h-40 overflow-auto">
                    <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(props.phases, null, 2) }}</pre>
                  </div>
                </div>
                
                <!-- Modules -->
                <div class="mb-4">
                  <h5 class="font-semibold text-blue-600 dark:text-blue-400 mb-2">Modules ({{ props.modules?.length || 0 }} items)</h5>
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border max-h-40 overflow-auto">
                    <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(props.modules, null, 2) }}</pre>
                  </div>
                </div>
                
                <!-- Journey -->
                <div class="mb-4">
                  <h5 class="font-semibold text-blue-600 dark:text-blue-400 mb-2">Journey Info</h5>
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                    <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(props.journey, null, 2) }}</pre>
                  </div>
                </div>
                
                <!-- User -->
                <div class="mb-4">
                  <h5 class="font-semibold text-blue-600 dark:text-blue-400 mb-2">User Data</h5>
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                    <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(props.user, null, 2) }}</pre>
                  </div>
                </div>

                <!-- Auth User (completo do AppHeader) -->
                <div class="mb-4">
                  <h5 class="font-semibold text-blue-600 dark:text-blue-400 mb-2">Auth User (Inertia Page Props)</h5>
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                    <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(page.props.auth.user, null, 2) }}</pre>
                  </div>
                </div>
              </div>

              <!-- Seção: Dados computados -->
              <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                <h4 class="font-bold text-green-700 dark:text-green-300 mb-3 text-sm">🧮 Dados Computados</h4>
                
                <!-- Flags importantes -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                    <h5 class="font-semibold text-green-600 dark:text-green-400 mb-2">Flags de Estado</h5>
                    <div class="space-y-1">
                      <div><strong>hasMultipleLaws:</strong> {{ hasMultipleLaws }}</div>
                      <div><strong>shouldUseModules:</strong> {{ shouldUseModules }}</div>
                      <div><strong>showModuleView:</strong> {{ showModuleView }}</div>
                      <div><strong>windowWidth:</strong> {{ windowWidth }}</div>
                    </div>
                  </div>
                  
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                    <h5 class="font-semibold text-green-600 dark:text-green-400 mb-2">Contadores</h5>
                    <div class="space-y-1">
                      <div><strong>Total Phases:</strong> {{ props.phases?.length || 0 }}</div>
                      <div><strong>Total Modules:</strong> {{ props.modules?.length || 0 }}</div>
                      <div><strong>Reference Groups:</strong> {{ Object.keys(phasesByReference).length }}</div>
                      <div><strong>Journey Current/Total:</strong> {{ props.journey?.current || 0 }}/{{ props.journey?.total || 0 }}</div>
                    </div>
                  </div>
                </div>
                
                <!-- Phases agrupadas por referência -->
                <div class="mb-4">
                  <h5 class="font-semibold text-green-600 dark:text-green-400 mb-2">Phases by Reference</h5>
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border max-h-40 overflow-auto">
                    <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(phasesByReference, null, 2) }}</pre>
                  </div>
                </div>
                
                <!-- Reference Groups -->
                <div class="mb-4">
                  <h5 class="font-semibold text-green-600 dark:text-green-400 mb-2">Reference Groups</h5>
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border max-h-40 overflow-auto">
                    <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(referenceGroups, null, 2) }}</pre>
                  </div>
                </div>
              </div>

              <!-- Seção: Informações de Admin e Subscrição (do AppHeader) -->
              <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
                <h4 class="font-bold text-indigo-700 dark:text-indigo-300 mb-3 text-sm">👑 Informações de Admin e Subscrição</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- Status de Admin -->
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                    <h5 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-2">Status de Admin</h5>
                    <div class="space-y-1 text-sm">
                      <div class="p-2 rounded" :class="isAdmin ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200'">
                        <strong>is_admin:</strong> {{ isAdmin ? 'true' : 'false' }}
                      </div>
                      <div><strong>user_id:</strong> {{ page.props.auth.user?.id || 'N/A' }}</div>
                      <div><strong>user_name:</strong> {{ page.props.auth.user?.name || 'N/A' }}</div>
                      <div><strong>user_email:</strong> {{ page.props.auth.user?.email || 'N/A' }}</div>
                    </div>
                  </div>
                  
                  <!-- Informações de Subscrição -->
                  <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                    <h5 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-2">Subscrição e Vidas</h5>
                    <div class="space-y-1 text-sm">
                      <div class="p-2 rounded" :class="page.props.auth.user?.has_infinite_lives ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200'">
                        <strong>has_infinite_lives:</strong> {{ page.props.auth.user?.has_infinite_lives ? 'true' : 'false' }}
                      </div>
                      <div><strong>lives:</strong> {{ page.props.auth.user?.lives || 0 }}</div>
                      <div v-if="page.props.auth.user?.debug_info">
                        <strong>has_active_subscription:</strong> {{ page.props.auth.user?.debug_info.has_active_subscription ? 'true' : 'false' }}
                      </div>
                      <div v-if="page.props.auth.user?.debug_info">
                        <strong>on_trial:</strong> {{ page.props.auth.user?.debug_info.on_trial ? 'true' : 'false' }}
                      </div>
                      <div v-if="page.props.auth.user?.debug_info">
                        <strong>subscribed:</strong> {{ page.props.auth.user?.debug_info.subscribed ? 'true' : 'false' }}
                      </div>
                      <div v-if="page.props.auth.user?.debug_info">
                        <strong>trial_ends_at:</strong> {{ page.props.auth.user?.debug_info.trial_ends_at || 'N/A' }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Seção: Análise de possíveis problemas -->
              <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
                <h4 class="font-bold text-yellow-700 dark:text-yellow-300 mb-3 text-sm">⚠️ Diagnóstico Automático</h4>
                
                <div class="space-y-2 text-sm">
                  <!-- Verificações de sanidade -->
                  <div class="p-2 rounded" :class="(props.phases && props.phases.length > 0) ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200'">
                    ✓ Phases Array: {{ (props.phases && props.phases.length > 0) ? 'OK' : 'VAZIO' }} ({{ props.phases?.length || 0 }} items)
                  </div>
                  
                  <div class="p-2 rounded" :class="(props.modules && props.modules.length > 0) ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200'">
                    ✓ Modules Array: {{ (props.modules && props.modules.length > 0) ? 'OK' : 'VAZIO' }} ({{ props.modules?.length || 0 }} items)
                  </div>
                  
                  <div class="p-2 rounded" :class="props.journey ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200'">
                    ✓ Journey Info: {{ props.journey ? 'OK' : 'AUSENTE' }}
                  </div>
                  
                  <div class="p-2 rounded" :class="hasMultipleLaws ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200'">
                    📚 Múltiplas Leis: {{ hasMultipleLaws ? 'SIM' : 'NÃO' }} ({{ Object.keys(phasesByReference).length }} referências)
                  </div>
                  
                  <div class="p-2 rounded" :class="shouldUseModules ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-200' : 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200'">
                    🏗️ Usando Módulos: {{ shouldUseModules ? 'SIM' : 'NÃO' }}
                  </div>
                  
                  <!-- Verificar se há fases com is_current -->
                  <div class="p-2 rounded" :class="(props.phases && props.phases.some(p => p.is_current)) ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200'">
                    🎯 Fase Atual Identificada: {{ (props.phases && props.phases.some(p => p.is_current)) ? 'SIM' : 'NÃO' }}
                  </div>
                  
                  <!-- Verificar consistência de IDs -->
                  <div class="p-2 rounded bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200">
                    🔢 IDs de Fases: {{ (props.phases && props.phases.map(p => p.id).join(', ')) || 'N/A' }}
                  </div>
                </div>
              </div>

              <!-- Seção: Análise específica do problema de cores -->
              <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
                <h4 class="font-bold text-red-700 dark:text-red-300 mb-3 text-sm">🎨 Análise de Cores das Fases</h4>
                
                <div class="space-y-2 text-xs max-h-60 overflow-auto">
                  <div v-for="phase in props.phases?.slice(0, 10)" :key="phase.id" class="p-2 rounded border">
                    <div class="font-semibold">Fase {{ phase.id }} - {{ phase.title }}</div>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                      <div>
                        <strong>Backend Data:</strong><br>
                        • is_complete: {{ phase.is_complete }}<br>
                        • is_fully_complete: {{ phase.progress?.is_fully_complete }}<br>
                        • all_attempted: {{ phase.progress?.all_attempted }}<br>
                        • is_blocked: {{ phase.is_blocked }}<br>
                        • is_current: {{ phase.is_current }}
                      </div>
                      <div>
                        <strong>Frontend Computed:</strong><br>
                        • isPhaseComplete(): {{ isPhaseComplete(phase) }}<br>
                        • Should be GREEN: {{ isPhaseComplete(phase) ? 'YES' : 'NO' }}<br>
                        • CSS Class: {{ isPhaseComplete(phase) ? 'bg-green-500' : (phase.is_current ? 'bg-blue-500' : (phase.is_blocked ? 'bg-gray-400' : 'bg-gray-300')) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Botão para copiar debug info -->
              <div class="flex justify-center mt-4">
                <button
                  @click="copyFullDebugInfo"
                  class="flex items-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                    <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                  </svg>
                  Copiar Debug Info
                </button>
              </div>

            </div>
          </div>
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
            :href="route('play.map', { jornada: props.journey.current - 1 })"
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
            :href="route('play.map', { jornada: props.journey.current + 1 })"
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
