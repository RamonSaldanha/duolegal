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
            class="mz-choice h-14 w-14 justify-center p-0"
            :class="{ 'is-selected': modelValue === color }"
            @click="emit('update:modelValue', color)"
        >
            <span class="block h-9 w-9 rounded-xl ring-1 ring-black/10" :style="{ backgroundColor: color }" />
        </button>
    </div>
</template>
