<script setup lang="ts">
import { buildAvatarSvg, type AvatarConfig, type AvatarCrop } from '@/lib/avatar';
import { computed } from 'vue';

interface Props {
    /** Config completo já com a opção desta peça aplicada. */
    preview: AvatarConfig;
    label: string;
    selected: boolean;
    /** Recorte do viewBox: rosto para peças da cabeça, tronco para roupa. */
    crop: AvatarCrop;
    uid: string;
}

const props = defineProps<Props>();

const svg = computed(() => buildAvatarSvg(props.preview, { uid: props.uid, crop: props.crop, background: false }));
const ratio = computed(() => `${props.crop[2]} / ${props.crop[3]}`);
</script>

<template>
    <button
        type="button"
        :aria-pressed="selected"
        :class="[
            'flex flex-col items-center gap-1.5 rounded-2xl border-2 p-2 transition-colors',
            selected
                ? 'border-purple-500 bg-purple-50 dark:border-purple-400 dark:bg-purple-500/15'
                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
        ]"
    >
        <!-- eslint-disable-next-line vue/no-v-html -- SVG gerado pelo app a partir de um config já normalizado -->
        <span class="block w-full overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800 [&>svg]:block [&>svg]:w-full" :style="{ aspectRatio: ratio }" v-html="svg" />
        <span :class="['text-[11px] font-bold leading-tight', selected ? 'text-purple-600 dark:text-purple-300' : 'text-gray-500 dark:text-gray-400']">
            {{ label }}
        </span>
    </button>
</template>
