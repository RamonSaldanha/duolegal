<script setup lang="ts">
import { cn } from '@/lib/utils'
import { TabsList, type TabsListProps } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<TabsListProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <TabsList
    v-bind="delegatedProps"
    :class="cn(
      'inline-flex items-center justify-center gap-1 rounded-[14px] bg-muted p-1 text-muted-foreground shadow-[inset_0_2px_0_var(--mz-press)]',
      props.class,
    )"
  >
    <slot />
  </TabsList>
</template>
