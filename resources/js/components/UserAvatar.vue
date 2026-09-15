<script setup lang="ts">
import { buildAvatarSvg, hasAvatar, type AvatarConfig } from '@/lib/avatar';
import { getInitials } from '@/composables/useInitials';
import { computed } from 'vue';

interface Props {
    /** `avatar_config` do usuário. Nulo para quem nunca editou — cai nas iniciais. */
    config?: Partial<AvatarConfig> | null;
    name?: string;
    /** Classes do fallback de iniciais (o ranking usa cor por posição). */
    fallbackClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    config: null,
    name: '',
    fallbackClass: 'bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white',
});

// Cada SVG na página precisa de ids de clipPath próprios, senão um recorta o outro.
const uid = Math.random().toString(36).slice(2, 9);

const drawn = computed(() => hasAvatar(props.config));
const svg = computed(() => (drawn.value ? buildAvatarSvg(props.config, { uid }) : ''));
</script>

<template>
    <!--
        Sem arredondamento próprio: a moldura é decisão do layout. Quem usa passa
        `rounded-full`, `rounded-xl` ou o que fizer sentido no lugar.
    -->
    <div class="relative shrink-0 overflow-hidden" role="img" :aria-label="name ? `Avatar de ${name}` : 'Avatar'">
        <!-- eslint-disable-next-line vue/no-v-html -- SVG gerado pelo próprio app; o config passa por normalizeAvatarConfig antes de virar markup -->
        <span v-if="drawn" class="block h-full w-full [&>svg]:h-full [&>svg]:w-full" v-html="svg" />
        <span v-else :class="['flex h-full w-full items-center justify-center font-semibold', fallbackClass]">
            {{ getInitials(name) }}
        </span>
    </div>
</template>
