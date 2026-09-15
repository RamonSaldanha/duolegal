<script setup lang="ts">
import { buildAvatarSvg, type AvatarConfig } from '@/lib/avatar';
import { Shuffle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    config: AvatarConfig;
}

const props = defineProps<Props>();
const emit = defineEmits<{ shuffle: [] }>();

const svg = computed(() => buildAvatarSvg(props.config, { uid: 'palco' }));
</script>

<template>
    <div class="sticky top-0 z-10 -mx-4 flex items-center justify-center border-b border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900">
        <!-- eslint-disable-next-line vue/no-v-html -- SVG gerado pelo app a partir de um config já normalizado -->
        <div class="w-40 max-w-[45vw] overflow-hidden rounded-3xl [&>svg]:block [&>svg]:w-full" v-html="svg" />

        <button
            type="button"
            class="absolute right-0 top-4 flex items-center gap-1.5 rounded-full border-2 border-gray-200 bg-white px-3.5 py-2 text-xs font-bold text-gray-600 transition-colors hover:border-purple-400 hover:text-purple-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-purple-400 dark:hover:text-purple-300"
            @click="emit('shuffle')"
        >
            <Shuffle class="h-4 w-4" />
            Sortear
        </button>
    </div>
</template>
