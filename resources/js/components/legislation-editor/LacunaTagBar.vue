<script setup lang="ts">
import type { SegmentLacuna } from '@/types/legislation';
import { X } from 'lucide-vue-next';

defineProps<{
    lacunas: SegmentLacuna[];
}>();

const emit = defineEmits<{
    removeLacuna: [uuid: string];
}>();
</script>

<template>
    <div v-if="lacunas.length > 0" class="flex flex-wrap gap-1.5 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
        <span
            v-for="lac in lacunas"
            :key="lac.uuid"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs"
            :class="lac.is_correct
                ? 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'
                : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400'"
        >
            <span v-if="lac.is_correct && lac.gap_order" class="text-[10px] text-gray-400 font-mono">{{ lac.gap_order }}.</span>
            {{ lac.word }}
            <button
                @click.stop="emit('removeLacuna', lac.uuid)"
                class="text-gray-400 hover:text-red-500 ml-0.5"
            >
                <X class="w-3 h-3" />
            </button>
        </span>
    </div>
</template>
