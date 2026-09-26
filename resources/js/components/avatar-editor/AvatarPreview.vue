<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { hasAvatar, normalizeAvatarConfig, type AvatarConfig } from '@/lib/avatar';
import { computed, ref, watch } from 'vue';

interface Props {
    config?: Partial<AvatarConfig> | null;
    name?: string;
    /**
     * Gruda no topo ao rolar. Só o editor precisa: lá o palco tem de acompanhar
     * quem está rolando a lista de peças.
     */
    sticky?: boolean;
    /**
     * Anima o boneco a cada troca de peça ou de cor: a cabeça cai amassada de
     * olhos fechados e volta esticada, abrindo os olhos. Só o editor liga.
     */
    animateChanges?: boolean;
}

const props = withDefaults(defineProps<Props>(), { config: null, name: '', sticky: false, animateChanges: false });

/**
 * Liga na primeira troca, e não ao abrir a página, e fica ligado. Não precisa
 * religar: cada troca redesenha o SVG, e o desenho novo já entra na página
 * tocando a animação do começo.
 */
const reacting = ref(false);

watch(
    () => props.config,
    () => {
        reacting.value = props.animateChanges;
    },
    { deep: true },
);

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
            reacting ? 'avatar-react' : '',
        ]"
        :style="stageColor ? { backgroundColor: stageColor } : undefined"
    >
        <!--
            Com a animação, o desenho pode sair do quadrado: esticada, a cabeça
            sobe até 17 acima do topo, e o cabelo seria cortado. Ele sobra para o
            `pt-6` do palco, que tem a mesma cor do fundo, então não aparece
            emenda.
        -->
        <UserAvatar
            :config="config"
            :name="name"
            :animated="animateChanges"
            class="aspect-square w-40 max-w-[45vw] text-3xl"
            :class="[drawn ? '' : 'rounded-3xl', animateChanges ? '!overflow-visible [&_svg]:!overflow-visible' : '']"
        />

        <!-- Ação do canto superior direito: sortear no editor, editar no perfil. -->
        <div class="absolute right-4 top-4">
            <slot />
        </div>
    </div>
</template>

<style scoped>
/*
 * Reação à troca de peça, decalcada de um vídeo de referência (60 quadros por
 * segundo). No quadro seguinte ao da troca a cabeça já cai e amassa, de olhos
 * fechados; sobe esticada enquanto os olhos reabrem de baixo para cima; e
 * assenta. Os deslocamentos são os de lá, convertidos para a grade do avatar
 * (cabeça de 132 de altura): a queda chega a 14,1 e a subida, a 12,3.
 *
 * O corpo vai junto. Lá o topo dos ombros desce e sobe exatamente com o
 * queixo, e a base fica presa no pé do quadro. Aqui o corpo amassa e estica
 * preso ao pé da tela, na medida em que o topo acompanhe o queixo; como cada
 * corpo tem uma altura, ela vem do SVG em `--avatar-body-h`.
 *
 * Com `transform-box: view-box`, a origem é medida na grade do SVG: o queixo
 * (100,152) para a cabeça e o pescoço, e o pé da tela (100,200) para o corpo.
 * O que compõe cada parte está marcado em `lib/avatar/render.ts`.
 */
.avatar-react :deep(.avatar-head) {
    transform-box: view-box;
    transform-origin: 100px 152px;
    animation: avatar-bounce 450ms linear;
}

.avatar-react :deep(.avatar-body) {
    transform-box: view-box;
    transform-origin: 100px 200px;
    animation: avatar-squash 450ms linear;
}

.avatar-react :deep(.avatar-lid) {
    animation: avatar-blink 450ms linear;
}

@keyframes avatar-bounce {
    0% {
        transform: translateY(14.1px) scale(1.05, 0.95);
    }
    6% {
        transform: translateY(12.3px) scale(1.05, 0.95);
    }
    11% {
        transform: translateY(8.8px) scale(1.03, 0.96);
    }
    17% {
        transform: translateY(2.6px) scale(1.01, 0.98);
    }
    24% {
        transform: translateY(-4.4px) scale(0.99, 1.01);
    }
    33% {
        transform: translateY(-12.3px) scale(0.96, 1.05);
    }
    43% {
        transform: translateY(-12.3px) scale(0.96, 1.04);
    }
    48% {
        transform: translateY(-10.6px) scale(0.97, 1.04);
    }
    54% {
        transform: translateY(-7px) scale(0.98, 1.03);
    }
    59% {
        transform: translateY(-3.5px) scale(0.99, 1.01);
    }
    65% {
        transform: translateY(-0.9px) scale(1, 0.99);
    }
    70% {
        transform: translateY(1.8px) scale(1.01, 0.99);
    }
    76% {
        transform: translateY(3.5px) scale(1.01, 0.99);
    }
    100% {
        transform: none;
    }
}

/* O topo do corpo desce e sobe o mesmo que o queixo, quadro a quadro. */
@keyframes avatar-squash {
    0% {
        transform: scaleY(calc(1 - 14.1 / var(--avatar-body-h)));
    }
    6% {
        transform: scaleY(calc(1 - 12.3 / var(--avatar-body-h)));
    }
    11% {
        transform: scaleY(calc(1 - 8.8 / var(--avatar-body-h)));
    }
    17% {
        transform: scaleY(calc(1 - 2.6 / var(--avatar-body-h)));
    }
    24% {
        transform: scaleY(calc(1 + 4.4 / var(--avatar-body-h)));
    }
    33% {
        transform: scaleY(calc(1 + 12.3 / var(--avatar-body-h)));
    }
    43% {
        transform: scaleY(calc(1 + 12.3 / var(--avatar-body-h)));
    }
    48% {
        transform: scaleY(calc(1 + 10.6 / var(--avatar-body-h)));
    }
    54% {
        transform: scaleY(calc(1 + 7 / var(--avatar-body-h)));
    }
    59% {
        transform: scaleY(calc(1 + 3.5 / var(--avatar-body-h)));
    }
    65% {
        transform: scaleY(calc(1 + 0.9 / var(--avatar-body-h)));
    }
    70% {
        transform: scaleY(calc(1 - 1.8 / var(--avatar-body-h)));
    }
    76% {
        transform: scaleY(calc(1 - 3.5 / var(--avatar-body-h)));
    }
    100% {
        transform: none;
    }
}

/*
 * A pálpebra (`eyelid()` em `parts/face.ts`) desce 31 e cobre o olho inteiro
 * até a cabeça começar a subir. Depois sobe em quatro tempos, como lá: o olho
 * aparece com 37%, 61%, 80% e 100% da altura, sempre de baixo para cima.
 */
@keyframes avatar-blink {
    0%,
    26% {
        transform: translateY(31px);
    }
    30% {
        transform: translateY(20px);
    }
    37% {
        transform: translateY(13.5px);
    }
    42% {
        transform: translateY(8.4px);
    }
    48%,
    100% {
        transform: none;
    }
}

/*
 * Com "reduzir movimento" ligado no sistema (no Windows, os efeitos de animação
 * desligados), a cabeça e o corpo não se mexem: só os olhos piscam, que é
 * movimento pequeno e continua avisando que a peça trocou.
 */
@media (prefers-reduced-motion: reduce) {
    .avatar-react :deep(.avatar-head),
    .avatar-react :deep(.avatar-body) {
        animation: none;
    }
}
</style>
