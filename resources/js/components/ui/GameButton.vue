<template>
  <button
    :class="[
      'game-button font-bold rounded-lg border-4 transition-all hover:transform hover:translate-y-1 disabled:cursor-not-allowed disabled:transform-none',
      variantClasses,
      sizeClasses,
      disabled && 'disabled:opacity-50'
    ]"
    :style="buttonStyle"
    :disabled="disabled"
    @mouseover="handleMouseOver"
    @mouseout="handleMouseOut"
    v-bind="$attrs"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

interface Props {
  variant?: 'white' | 'green' | 'purple' | 'red' | 'blue'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'white',
  size: 'md',
  disabled: false
})

const isHovered = ref(false)

const variantClasses = computed(() => {
  const variants = {
    white: 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white border-gray-300 dark:border-gray-600',
    green: 'bg-green-500 hover:bg-green-600 text-white border-green-700',
    purple: 'bg-purple-500 hover:bg-purple-600 disabled:bg-gray-400 text-white border-purple-700 disabled:border-gray-600',
    red: 'bg-red-500 hover:bg-red-600 text-white border-red-700',
    blue: 'bg-blue-500 hover:bg-blue-600 text-white border-blue-700'
  }
  return variants[props.variant]
})

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2',
    lg: 'px-6 py-3 text-lg'
  }
  return sizes[props.size]
})

const buttonStyle = computed(() => {
  const shadowColors = {
    white: '#d4d4d4', // Gray-300 neutro customizado for light mode  
    green: '#15803d', // green-700
    purple: props.disabled ? '#525252' : '#7c3aed', // gray-600 neutro or purple-700
    red: '#b91c1c', // red-700
    blue: '#1d4ed8' // blue-700
  }
  
  // Para o botão branco, precisa usar a mesma cor da borda (gray-300/gray-600)
  if (props.variant === 'white') {
    const lightShadowColor = '#d4d4d4' // gray-300 neutro customizado - same as border
    const darkShadowColor = '#525252' // gray-600 neutro customizado - same as border
    const shadowHeight = isHovered.value ? '2px' : '4px'
    
    return {
      boxShadow: `0 ${shadowHeight} 0 ${lightShadowColor}`,
      // Sombra específica para dark mode usando CSS custom properties
      '--dark-shadow': `0 ${shadowHeight} 0 ${darkShadowColor}`,
    }
  }
  
  // Para botão purple (Verificar), deve ter sombra sólida sempre
  if (props.variant === 'purple') {
    const shadowHeight = isHovered.value ? '2px' : '4px'
    return {
      boxShadow: `0 ${shadowHeight} 0 ${shadowColors[props.variant]}`
    }
  }
  
  // Para outros botões
  const shadowHeight = isHovered.value ? '2px' : '4px'
  
  return {
    boxShadow: `0 ${shadowHeight} 0 ${shadowColors[props.variant]}`
  }
})

const handleMouseOver = (event: MouseEvent) => {
  if (!props.disabled) {
    isHovered.value = true
  }
}

const handleMouseOut = (event: MouseEvent) => {
  isHovered.value = false
}
</script>

<style scoped>
.game-button:hover {
  transform: translateY(2px);
}

.game-button:active {
  transform: translateY(4px);
  box-shadow: none !important;
}

.game-button.disabled {
  transform: none !important;
}

/* Dark mode support for white variant shadow */
@media (prefers-color-scheme: dark) {
  .game-button.dark\:bg-gray-700 {
    box-shadow: var(--dark-shadow) !important;
  }
}

/* Class-based dark mode support (if using Tailwind's dark: prefix) */
:global(.dark) .game-button.dark\:bg-gray-700 {
  box-shadow: var(--dark-shadow) !important;
}
</style>