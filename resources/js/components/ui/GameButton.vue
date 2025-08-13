<template>
  <Link
    v-if="href"
    :href="href"
    :class="[
      'game-button font-bold rounded-lg border-4 transition-all hover:transform hover:translate-y-1 disabled:cursor-not-allowed disabled:transform-none inline-flex items-center justify-center',
      variantClasses,
      sizeClasses,
      disabled && 'disabled:opacity-50'
    ]"
    :style="buttonStyle"
    @mouseover="handleMouseOver"
    @mouseout="handleMouseOut"
    v-bind="otherAttrs"
  >
    <slot />
  </Link>
  <button
    v-else
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
    v-bind="otherAttrs"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, useAttrs } from 'vue'
import { Link } from '@inertiajs/vue3'

interface Props {
  variant?: 'white' | 'green' | 'purple' | 'red' | 'blue'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
  href?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'white',
  size: 'md',
  disabled: false,
  href: undefined
})

const attrs = useAttrs()

// Filter out href from attrs when using as Link
const otherAttrs = computed(() => {
  const { href: _, ...rest } = attrs as any
  return rest
})

const isHovered = ref(false)
const isDark = ref(false)

// Detectar mudanças no tema
const updateTheme = () => {
  isDark.value = document.documentElement.classList.contains('dark')
}

onMounted(() => {
  updateTheme()
  
  // Observer para mudanças na classe dark do html
  const observer = new MutationObserver(updateTheme)
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class']
  })
  
  // Cleanup no unmount
  onUnmounted(() => {
    observer.disconnect()
  })
})

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
  const shadowHeight = isHovered.value ? '2px' : '4px'
  
  // Para o botão branco, usar cores diferentes baseado no tema atual
  if (props.variant === 'white') {
    const shadowColor = isDark.value ? '#525252' : '#d4d4d4' // gray-600 dark : gray-300 light
    return {
      boxShadow: `0 ${shadowHeight} 0 ${shadowColor}`
    }
  }
  
  // Para outros botões, cores fixas
  const shadowColors = {
    green: '#15803d', // green-700
    purple: props.disabled ? '#525252' : '#7c3aed', // gray-600 neutro or purple-700
    red: '#b91c1c', // red-700
    blue: '#1d4ed8' // blue-700
  }
  
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
</style>