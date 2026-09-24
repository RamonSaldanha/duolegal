<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { hasAvatar, normalizeAvatarConfig, type AvatarConfig } from '@/lib/avatar';
import { computed } from 'vue';

interface Props {
    config?: Partial<AvatarConfig> | null;
    name?: string;
    /**
     * Gruda no topo ao rolar. Só o editor precisa: lá o palco tem de acompanhar
     * quem está rolando a lista de peças.
     */
    sticky?: boolean;
}

const props = withDefaults(defineProps<Props>(), { config: null, name: '', sticky: false });

const drawn = computed(() => hasAvatar(props.config));

/**
 * A cor de fundo escolhida pinta o palco **inteiro**, não só o quadrado do meio —
 * senão ela vira um selo no meio de uma faixa cinza e a pessoa não consegue julgar
 * a cor que acabou de escolher.
 *
 * Quem nunca salvou um avatar não tem cor escolhida: aí o palco fica neutro e o
 * UserAvatar cai nas iniciais. Pintar de uma cor que o usuário nunca escolheu seria
 * inventar uma preferência que não existe.
 */
const stageColor = computed(() => (drawn.value ? normalizeAvatarConfig(props.config).background : null));
</script>

<template>
    <!--
        O boneco encosta no **pé** do palco (`items-end` sem padding embaixo), não
        no meio dele.

        O desenho é um quadrado de 200x200 em que o tronco vai até a linha 200, ou
        seja, ele termina cortado por projeto — é ombro, não corpo inteiro. Centrado
        no palco esse corte fica no ar, com fundo embaixo, e lê como desenho
        decepado. Encostando na borda de baixo o corte sai da vista: o tronco
        simplesmente continua para fora do cartão.

        Daí o `overflow-hidden` junto do arredondamento: é ele que apara o tronco na
        curva do cartão.

        Sem avatar salvo não há tronco nenhum, só as iniciais — nesse caso o bloco
        volta a ser centrado, senão as letras ficariam grudadas na borda.
    -->
    <div
        class="relative flex justify-center overflow-hidden rounded-2xl px-4"
        :class="[
            sticky ? 'sticky top-0 z-10' : '',
            drawn ? 'items-end pt-6' : 'items-center py-6',
            stageColor ? '' : 'bg-gray-50 dark:bg-gray-900',
        ]"
        :style="stageColor ? { backgroundColor: stageColor } : undefined"
    >
        <UserAvatar :config="config" :name="name" class="aspect-square w-40 max-w-[45vw] text-3xl" :class="drawn ? '' : 'rounded-3xl'" />

        <!-- Ação do canto superior direito: sortear no editor, editar no perfil. -->
        <div class="absolute right-4 top-4">
            <slot />
        </div>
    </div>
</template>
