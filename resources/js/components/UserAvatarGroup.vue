<script setup lang="ts">
import { ref, computed } from 'vue';
import { Users } from 'lucide-vue-next';
import { getInitials } from '@/composables/useInitials';

interface User {
  id: number;
  name: string;
}

interface Props {
  users: User[];
  maxVisible?: number;
  size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
  maxVisible: 3,
  size: 'md'
});

const emit = defineEmits<{
  'show-all': [users: User[]];
}>();

const showTooltip = ref<number | null>(null);

const visibleUsers = computed(() => props.users.slice(0, props.maxVisible));
const hiddenCount = computed(() => Math.max(0, props.users.length - props.maxVisible));

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return {
        avatar: 'w-6 h-6',
        text: 'text-xs',
        overlap: '-ml-1' // Reduzir sobreposição para mostrar melhor múltiplos usuários
      };
    case 'lg':
      return {
        avatar: 'w-10 h-10',
        text: 'text-sm',
        overlap: '-ml-2' // Reduzir sobreposição
      };
    default: // md
      return {
        avatar: 'w-8 h-8',
        text: 'text-xs',
        overlap: '-ml-1' // Reduzir sobreposição
      };
  }
});

// Função removida - não usaremos avatares externos

const handleShowAll = () => {
  emit('show-all', props.users);
};
</script>

<template>
  <div v-if="props.users.length > 0" class="flex items-center relative">
    <!-- Avatares visíveis -->
    <div class="flex items-center">
      <div
        v-for="(user, index) in visibleUsers"
        :key="user.id"
        class="relative group"
        :class="index > 0 ? sizeClasses.overlap : ''"
        @mouseenter="showTooltip = user.id"
        @mouseleave="showTooltip = null"
      >
        <!-- Avatar do usuário -->
        <div
          :class="[
            sizeClasses.avatar,
            'rounded-full border-2 border-white dark:border-gray-800 bg-blue-600 dark:bg-blue-500 text-white flex items-center justify-center font-bold cursor-pointer transition-all duration-200 hover:scale-110 hover:z-20 relative shadow-lg'
          ]"
          :style="{ zIndex: visibleUsers.length - index + 10 }"
        >
          <!-- Iniciais do nome -->
          <span
            :class="sizeClasses.text"
          >
            {{ getInitials(user.name) }}
          </span>
        </div>

        <!-- Tooltip com nome do usuário -->
        <div
          v-if="showTooltip === user.id"
          class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-50"
        >
          <div class="bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 px-2 py-1 rounded text-xs whitespace-nowrap shadow-lg">
            {{ user.name }}
            <!-- Seta do tooltip -->
            <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-2 border-r-2 border-t-2 border-l-transparent border-r-transparent border-t-gray-900 dark:border-t-gray-100"></div>
          </div>
        </div>
      </div>

      <!-- Indicador de usuários ocultos -->
      <div
        v-if="hiddenCount > 0"
        :class="[
          sizeClasses.avatar,
          sizeClasses.overlap,
          'rounded-full border-2 border-white dark:border-gray-800 bg-gray-600 dark:bg-gray-500 text-white flex items-center justify-center font-bold cursor-pointer transition-transform hover:scale-110 hover:z-10 relative shadow-lg'
        ]"
        :style="{ zIndex: 5 }"
        @click="handleShowAll"
        @mouseenter="showTooltip = -1"
        @mouseleave="showTooltip = null"
      >
        <span :class="sizeClasses.text">+{{ hiddenCount }}</span>

        <!-- Tooltip para "ver todos" -->
        <div
          v-if="showTooltip === -1"
          class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-50"
        >
          <div class="bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 px-2 py-1 rounded text-xs whitespace-nowrap shadow-lg">
            Ver todos ({{ props.users.length }})
            <!-- Seta do tooltip -->
            <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-2 border-r-2 border-t-2 border-l-transparent border-r-transparent border-t-gray-900 dark:border-t-gray-100"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>