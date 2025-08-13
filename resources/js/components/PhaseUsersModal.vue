<script setup lang="ts">
import { computed } from 'vue';
import { X, Trophy, Users as UsersIcon } from 'lucide-vue-next';
import { getInitials } from '@/composables/useInitials';

interface User {
  id: number;
  name: string;
}

interface Props {
  show: boolean;
  users: User[];
  phaseNumber: number;
  phaseTitle?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'close': [];
}>();

// Função removida - não usaremos avatares externos

const handleClose = () => {
  emit('close');
};

const handleBackdropClick = (event: Event) => {
  if (event.target === event.currentTarget) {
    handleClose();
  }
};
</script>

<template>
  <!-- Modal Backdrop -->
  <div
    v-if="show"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    @click="handleBackdropClick"
  >
    <!-- Modal Content -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full max-h-[80vh] overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-primary/10 rounded-lg">
            <Trophy class="w-6 h-6 text-primary" />
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
              {{ phaseTitle || `Fase ${phaseNumber}` }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ users.length }} {{ users.length === 1 ? 'jogador' : 'jogadores' }} nesta fase
            </p>
          </div>
        </div>
        
        <button
          @click="handleClose"
          class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
        >
          <X class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        </button>
      </div>

      <!-- Users List -->
      <div class="p-6">
        <div v-if="users.length === 0" class="text-center py-8">
          <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
            <UsersIcon class="w-8 h-8 text-gray-400" />
          </div>
          <p class="text-gray-500 dark:text-gray-400">
            Nenhum jogador encontrado nesta fase
          </p>
        </div>

        <div v-else class="space-y-3 max-h-60 overflow-y-auto">
          <div
            v-for="user in users"
            :key="user.id"
            class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
          >
            <!-- Avatar do usuário -->
            <div class="relative">
              <div class="w-10 h-10 rounded-full border-2 border-gray-200 dark:border-gray-600 bg-primary text-white flex items-center justify-center font-bold shadow-sm">
                <!-- Iniciais do nome -->
                <span class="text-sm">
                  {{ getInitials(user.name) }}
                </span>
              </div>
              
              <!-- Indicador online (opcional - pode ser implementado depois) -->
              <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></div>
            </div>

            <!-- Informações do usuário -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                {{ user.name }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Competindo nesta fase
              </p>
            </div>

            <!-- Badge de competição (opcional) -->
            <div class="flex-shrink-0">
              <div class="px-2 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-xs font-medium rounded-full">
                🏆
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer (opcional) -->
      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
        <p class="text-xs text-center text-gray-500 dark:text-gray-400">
          Complete esta fase para avançar no desafio!
        </p>
      </div>
    </div>
  </div>
</template>