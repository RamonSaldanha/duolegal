<script setup lang="ts">
interface Props {
    colors: string[];
    modelValue: string;
    label: string;
}

defineProps<Props>();
const emit = defineEmits<{ 'update:modelValue': [color: string] }>();
</script>

<template>
    <div class="flex flex-wrap gap-2.5">
        <button
            v-for="color in colors"
            :key="color"
            type="button"
            :aria-label="`${label}: ${color}`"
            :aria-pressed="modelValue === color"
            :class="[
                'grid h-14 w-14 place-items-center rounded-2xl border-2 transition-colors',
                modelValue === color
                    ? 'border-purple-500 bg-purple-50 dark:border-purple-400 dark:bg-purple-500/15'
                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
            ]"
            @click="emit('update:modelValue', color)"
        >
            <span class="block h-9 w-9 rounded-xl ring-1 ring-black/10" :style="{ backgroundColor: color }" />
        </button>
    </div>
</template>
