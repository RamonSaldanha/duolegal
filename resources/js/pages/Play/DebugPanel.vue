<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Bug, X } from 'lucide-vue-next';

interface UserDebugInfo {
  has_active_subscription: boolean;
  on_trial: boolean;
  subscribed: boolean;
  trial_ends_at: string | null;
}

interface UserProps {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
  lives?: number;
  avatar?: string;
  has_infinite_lives?: boolean;
  debug_info?: UserDebugInfo;
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
  is_fully_complete?: boolean;
  all_attempted?: boolean;
  has_errors?: boolean;
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

interface User {
  lives: number;
  has_infinite_lives?: boolean;
  id?: number;
  name?: string;
  email?: string;
  xp?: number;
}

const props = defineProps<{
  phases: Phase[];
  modules?: OptimizedModule[];
  journey?: JourneyInfo;
  user?: User;
  showModuleView?: boolean;
  shouldUseModules?: boolean;
  windowWidth?: number;
}>();

// State
const show = ref(false);

// Page/auth
const page = usePage<{ auth: { user: UserProps | null } }>();
const isAdmin = computed(() => page.props.auth.user?.is_admin);

// Local helpers recomputed here to keep component independent
const phasesByReference = computed(() => {
  const grouped: Record<string, { name: string; phases: Phase[] }> = {};
  props.phases?.forEach((phase) => {
    if (!grouped[phase.reference_uuid]) {
      grouped[phase.reference_uuid] = { name: phase.reference_name, phases: [] };
    }
    grouped[phase.reference_uuid].phases.push(phase);
  });
  return grouped;
});

const hasMultipleLaws = computed(() => Object.keys(phasesByReference.value).length > 1);

const getPhasesByIds = (phaseIds: number[]): Phase[] => {
  const map = new Map(props.phases?.map((p) => [p.id, p]));
  return phaseIds.map((id) => map.get(id)).filter(Boolean) as Phase[];
};

const expandedModules = computed(() => {
  if (!props.modules || props.modules.length === 0) return [] as any[];
  return props.modules.map((m) => ({
    ...m,
    references: m.references.map((r) => ({
      reference_name: r.reference_name,
      reference_uuid: r.reference_uuid,
      phases: getPhasesByIds(r.phase_ids),
    })),
  }));
});

// Convert grouped phases to array for better indexing
const referenceGroups = computed(() => {
  return Object.entries(phasesByReference.value).map(([uuid, data]) => ({
    uuid,
    name: data.name,
    phases: data.phases
  }));
});

// Helper function for phase completion check
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

// Copy full debug info in a single JSON block
const copyFullDebugInfo = async (): Promise<void> => {
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
      shouldUseModules: props.shouldUseModules,
      showModuleView: props.showModuleView,
      windowWidth: props.windowWidth,
    },
    user: safeUser,
    journey: props.journey ?? null,
    counts: {
      phases: props.phases?.length ?? 0,
      modules: props.modules?.length ?? 0,
    },
    phases: props.phases ?? [],
    modules: props.modules ?? [],
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
  } catch (_err) {
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
</script>

<template>
  <div v-if="isAdmin" class="relative">
    <!-- Toggle button -->
    <button
      type="button"
      class="flex items-center gap-2 px-3 py-2 bg-red-500 text-white rounded-lg shadow-lg hover:bg-red-600 transition-colors"
      @click="show = !show"
    >
      <Bug class="w-4 h-4" />
      <span class="text-sm font-medium">Debug Map</span>
    </button>

    <!-- Modal -->
    <div 
      v-if="show" 
      class="fixed inset-4 bg-white dark:bg-gray-900 border-2 border-red-500 rounded-lg shadow-2xl z-40 overflow-hidden flex flex-col"
    >
      <!-- Header do painel -->
      <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
        <h3 class="text-lg font-bold text-red-700 dark:text-red-300 flex items-center gap-2">
          <Bug class="w-5 h-5" />
          Debug - PlayController → Map
        </h3>
        <button
          @click="show = false"
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
                  <div><strong>shouldUseModules:</strong> {{ props.shouldUseModules }}</div>
                  <div><strong>showModuleView:</strong> {{ props.showModuleView }}</div>
                  <div><strong>windowWidth:</strong> {{ props.windowWidth }}</div>
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
              
              <div class="p-2 rounded" :class="props.shouldUseModules ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-200' : 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200'">
                🏗️ Usando Módulos: {{ props.shouldUseModules ? 'SIM' : 'NÃO' }}
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
  </div>
</template>

<style scoped>
</style>

