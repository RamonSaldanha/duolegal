<script setup lang="ts">
import { buildAvatarSvg, type AvatarConfig, type AvatarCrop } from '@/lib/avatar';
import { Lock } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    /** Config completo já com a opção desta peça aplicada. */
    preview: AvatarConfig;
    label: string;
    selected: boolean;
    /** Recorte do viewBox: rosto para peças da cabeça, tronco para roupa. */
    crop: AvatarCrop;
    uid: string;
    /**
     * Opção exclusiva de assinante vista por quem não assina. Continua
     * clicável — o clique é o que abre o convite para assinar.
     */
    locked?: boolean;
}

const props = withDefaults(defineProps<Props>(), { locked: false });

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
        <span class="relative block w-full">
            <!-- eslint-disable-next-line vue/no-v-html -- SVG gerado pelo app a partir de um config já normalizado -->
            <span
                class="block w-full overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800 [&>svg]:block [&>svg]:w-full"
                :style="{ aspectRatio: ratio }"
                v-html="svg"
            />
            <span v-if="locked" class="absolute right-1.5 top-1.5 grid h-6 w-6 place-items-center rounded-full bg-gray-900/80 text-white">
                <Lock class="h-3.5 w-3.5" aria-hidden="true" />
            </span>
        </span>
        <span
            :class="['text-[11px] font-bold leading-tight', selected ? 'text-purple-600 dark:text-purple-300' : 'text-gray-500 dark:text-gray-400']"
        >
            {{ label }}
        </span>
        <span v-if="locked" class="text-[10px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">Assinantes</span>
    </button>
</template>
