<script setup lang="ts">
import GameButton from '@/components/ui/GameButton.vue';
import { useCountUp } from '@/composables/useCountUp';
import { FileCheck, Target, Zap } from 'lucide-vue-next';
import { toRef } from 'vue';

// PRÉVIA (ainda não usada no jogo): fim de fase.
// Linguagem do site: fundo sólido, cores chapadas, contorno de 2 px, sem degradê. A abertura é a
// vinheta (SceneShutter); quando ela abre, o marca-texto passa no título e XP e precisão contam.
const props = defineProps<{
    title: string;
    mark: string;
    count: number;
    countLabel: string;
    xp: number;
    // A parte do XP que veio do combo de quem assina (Plus), mostrada embaixo dos números
    comboXp?: number;
    accuracy: number;
}>();

const emit = defineEmits<{ continue: [event: MouseEvent] }>();

const { shown: shownXp } = useCountUp(toRef(props, 'xp'), { from: 0, delay: 700 });
const { shown: shownAccuracy } = useCountUp(toRef(props, 'accuracy'), { from: 0, delay: 800 });
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden bg-background">
        <div class="relative flex min-h-0 flex-1 flex-col overflow-y-auto px-4 pb-6">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col">
                <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                    <!-- Mesma moldura do convite. A arte tem 146 px vazios em cima (a do convite, 20): sobe 126/418 da largura -->
                    <div class="hero-gif">
                        <img src="/img/vencedora.gif" alt="" class="w-full" style="margin-top: -30.14%" />
                    </div>
                    <h1 class="mt-4 text-[1.75rem] font-bold leading-tight text-foreground">
                        {{ title }} <span class="mark">{{ mark }}</span>
                    </h1>
                    <p class="mt-2 text-base text-muted-foreground">Você está um passo mais perto da aprovação.</p>
                </div>

                <!-- Números da fase: uma caixa com divisórias, ícone e número na cor de cada um -->
                <div class="grid grid-cols-3 divide-x-2 divide-border rounded-2xl border-2 border-border">
                    <div class="min-w-0 px-1 py-3 text-center">
                        <div class="flex items-center justify-center gap-1.5 text-xl font-bold text-green-600 dark:text-green-400">
                            <FileCheck class="h-5 w-5" />
                            {{ count }}
                        </div>
                        <div class="mt-0.5 whitespace-nowrap text-[13px] text-muted-foreground sm:text-sm">{{ countLabel }}</div>
                    </div>
                    <div class="min-w-0 px-1 py-3 text-center">
                        <div class="flex items-center justify-center gap-1.5 text-xl font-bold tabular-nums text-xp-ink">
                            <Zap class="h-5 w-5 fill-current" />
                            {{ shownXp }}
                        </div>
                        <div class="mt-0.5 whitespace-nowrap text-[13px] text-muted-foreground sm:text-sm">XP recebido</div>
                    </div>
                    <div class="min-w-0 px-1 py-3 text-center">
                        <div class="flex items-center justify-center gap-1.5 text-xl font-bold tabular-nums text-brand-soft-ink dark:text-brand">
                            <Target class="h-5 w-5" />
                            {{ shownAccuracy }}%
                        </div>
                        <div class="mt-0.5 whitespace-nowrap text-[13px] text-muted-foreground sm:text-sm">Precisão</div>
                    </div>
                </div>

                <!-- Quanto o combo rendeu a mais, para quem assina -->
                <p v-if="comboXp && comboXp > 0" class="mt-3 flex items-center justify-center gap-1.5 text-sm font-bold text-xp-ink">
                    <Zap class="h-4 w-4 fill-current" />
                    +{{ comboXp }} XP dos combos, por ser <span class="text-plus-ink">Plus</span>
                </p>

                <div class="mt-6">
                    <GameButton variant="main" size="lg" class="w-full" @click="(e: MouseEvent) => emit('continue', e)">Continuar</GameButton>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Moldura das ilustrações das telas de fim (igual no convite Premium) */
.hero-gif {
    width: min(15rem, 32vh);
    aspect-ratio: 1;
    overflow: hidden;
}
.mark {
    position: relative;
    z-index: 0;
    white-space: nowrap;
}
.mark::before {
    content: '';
    position: absolute;
    z-index: -1;
    left: -4px;
    right: -4px;
    bottom: 0.08em;
    height: 0.42em;
    border-radius: 3px;
    background: var(--mz-brand-mark);
    transform-origin: left center;
}
@media (prefers-reduced-motion: no-preference) {
    .mark::before {
        animation: swipe 0.45s 0.75s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}

@keyframes swipe {
    from {
        scale: 0 1;
    }
    to {
        scale: 1 1;
    }
}
</style>
