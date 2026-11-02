<script setup lang="ts">
import { Star } from 'lucide-vue-next';

defineProps<{
    stars: number;
}>();
</script>

<template>
    <div class="flex items-center gap-0.5" role="img" :aria-label="`${stars} de 5 estrelas`">
        <Star
            v-for="position in 5"
            :key="position"
            class="h-4 w-4 fill-current stroke-none"
            :class="position <= stars ? 'text-amber-400' : 'text-gray-200 dark:text-gray-700'"
        />
    </div>
</template>
