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

const props = defineProps<{
  phases: Phase[];
  modules?: OptimizedModule[];
  journey?: JourneyInfo;
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

// Copy full debug info in a single JSON block
const copyFullDebugInfo = async (): Promise<void> => {
  const user = page.props.auth.user
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
    },
    user,
    journey: props.journey ?? null,
    counts: {
      phases: props.phases?.length ?? 0,
      modules: props.modules?.length ?? 0,
    },
    phases: props.phases ?? [],
    modules: props.modules ?? [],
    expandedModules: expandedModules.value ?? [],
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
  } catch (_) {
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
    } catch (err) {
      console.error('Falha ao copiar debug:', err);
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
      class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg border-2 border-gray-400 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm font-medium"
      @click="show = !show"
    >
      <Bug class="w-4 h-4" />
      <span>Debug Map</span>
    </button>

    <!-- Modal -->
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center"
    >
      <div class="absolute inset-0 bg-black/50" @click="show = false" />
      <div class="relative z-10 w-full max-w-3xl mx-4 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-xl shadow-lg border border-gray-300 dark:border-gray-700">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-800">
          <div class="flex items-center gap-2 font-semibold">
            <Bug class="w-5 h-5" />
            <span>Debug - PlayController → Map</span>
          </div>
          <button class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800" @click="show = false">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="px-5 py-4 text-sm space-y-3 max-h-[70vh] overflow-auto">
          <div>
            <div class="font-semibold mb-1">Resumo</div>
            <div>Fases: {{ props.phases?.length || 0 }}, Módulos: {{ props.modules?.length || 0 }}</div>
            <div v-if="props.journey">Jornada: {{ props.journey.current }} / {{ props.journey.total }} - {{ props.journey.journey_title }}</div>
          </div>

          <div class="flex gap-2">
            <button
              class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-500 text-white rounded-md border-2 border-blue-700 shadow hover:brightness-95"
              @click="copyFullDebugInfo"
            >
              Copiar Debug Completo
            </button>
            <button
              class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-md border-2 border-gray-300 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700"
              @click="show = false"
            >
              Fechar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  
</template>

<style scoped>
</style>

